<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves an employee's salary, benefits and bank details the way the legacy
 * accounting editbenefits.php did, so payroll (PayrollCalculationService,
 * PayslipService) and the dashboard keep reading the same values:
 *
 *   hris.employee_payroll   salary, salary_type, effective_date, previous_salary, bank, benefit amounts
 *   hris.employee_bank_info bank_name, account_number (IV + AES ciphertext, config('payroll.bank_encryption_*'))
 *   hris.audit_log          old / new employee_payroll row and "field: 'old' → 'new'" lines
 *
 * Differences from the legacy page: nothing is written when nothing changed,
 * the OTP secret (ga_secret) never goes into the audit log, a salary change
 * needs a new effective date, and an account number is only replaced or
 * removed when asked to (a blank field keeps it).
 */
class EmployeeBenefitsService
{
    private const HRIS_CONNECTION = 'hris';

    public const AMOUNT_FIELDS = ['sss', 'phic', 'hdmf', 'philcare', 'generali', 'fwd', 'tax', 'otherbenefits'];

    public const SALARY_TYPES = ['Rated' => 'Rated', 'Fixed' => 'Fixed', 'Daily' => 'Fixed Daily'];

    public const BANKS = ['BPI', 'PNB', 'Eastwest', 'RCBC', 'Gcash', 'Cash'];

    // Never copied into audit_log, never compared or shown
    public const HIDDEN_FIELDS = ['ga_secret'];

    // employee_payroll column → label on the form and in the change history
    public const FIELD_LABELS = [
        'salary'          => 'Salary Rate',
        'salary_type'     => 'Salary Type',
        'effective_date'  => 'Effective Date',
        'previous_salary' => 'Previous Salary',
        'sss'             => 'SSS',
        'phic'            => 'PhilHealth',
        'hdmf'            => 'Pag-IBIG',
        'philcare'        => 'PhilCare (HMO)',
        'generali'        => 'Life & Accident Insurance',
        'fwd'             => 'FWD Retirement',
        'tax'             => 'Withholding Tax',
        'otherbenefits'   => 'Other Benefits',
        'bank'            => 'Bank',
        'account_number'  => 'Bank Account',
        'email'           => 'Email',
    ];

    /**
     * @param  array  $input   validated: amounts (float), salary (float), salary_type, bank, effective_date (?Y-m-d),
     *                         account_action (keep|change|remove), account_number (?string)
     * @param  string $actor   full name of the accounting user (legacy addedby / updatedby / audit user)
     * @return array{changed: bool, fields: array<int, string>}
     */
    public function save(string $idno, array $input, string $actor, string $link, ?string $ip): array
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        return $conn->transaction(function () use ($conn, $idno, $input, $actor, $link, $ip) {
            $old = $conn->table('employee_payroll')->where('idno', $idno)->lockForUpdate()->first();
            $bankRow = $conn->table('employee_bank_info')->where('idno', $idno)->lockForUpdate()->first();
            $oldRow = $old ? (array) $old : [];

            // ---- New employee_payroll values ----
            $values = [];
            foreach (self::AMOUNT_FIELDS as $f) {
                $values[$f] = $input[$f];
            }
            $values['salary'] = $input['salary'];
            $values['salary_type'] = $input['salary_type'];
            $values['bank'] = $input['bank'];
            $values['effective_date'] = $input['effective_date'] ?: null;

            // Legacy rule: when the salary changes the old one becomes previous_salary,
            // otherwise previous_salary stays; empty / 0 is stored as NULL
            $salaryChanged = $old && !$this->same($old->salary, $values['salary']);
            $previous = $salaryChanged ? $old->salary : ($old->previous_salary ?? null);
            $values['previous_salary'] = $previous ? $previous : null;

            if ($salaryChanged && $this->dateOf($old->effective_date) === $values['effective_date']) {
                throw ValidationException::withMessages([
                    'effective_date' => 'Set a new effective date when changing the salary rate.',
                ]);
            }

            // ---- Bank account ----
            $action = $input['account_action'];
            $hadAccount = $bankRow && !empty($bankRow->account_number);
            $accountChange = match (true) {
                $action === 'change' => $hadAccount ? 'changed' : 'added',
                $action === 'remove' && $hadAccount => 'removed',
                default => null,
            };
            $bankNameChanged = ($bankRow->bank_name ?? '') !== $values['bank'];

            $payrollChanged = !$old || collect($values)->contains(fn ($v, $f) => !$this->same($old->$f ?? null, $v));

            if (!$payrollChanged && !$bankNameChanged && !$accountChange) {
                return ['changed' => false, 'fields' => []];
            }

            $now = now('Asia/Manila')->format('Y-m-d H:i:s');

            // ---- employee_bank_info (legacy: bank_name + account, addedby / addeddatetime refreshed) ----
            if ($bankNameChanged || $accountChange || !$bankRow) {
                $bankValues = ['bank_name' => $values['bank'], 'addedby' => $actor, 'addeddatetime' => $now];
                if ($accountChange === 'removed') {
                    $bankValues['account_number'] = null;
                } elseif ($accountChange) {
                    $bankValues['account_number'] = $this->encrypt($input['account_number']);
                }

                if ($bankRow) {
                    $conn->table('employee_bank_info')->where('idno', $idno)->update($bankValues);
                } else {
                    $conn->table('employee_bank_info')->insert(['idno' => $idno] + $bankValues + ['account_number' => null]);
                }
            }

            // ---- employee_payroll (legacy: update with updatedby, or insert with addedby) ----
            if ($old) {
                $conn->table('employee_payroll')->where('idno', $idno)->update($values + [
                    'updatedby'       => $actor,
                    'updateddatetime' => $now,
                ]);
            } else {
                $conn->table('employee_payroll')->insert(['idno' => $idno] + $values + [
                    'previous_salary' => null,
                    'addedby'         => $actor,
                    'addeddatetime'   => $now,
                ]);
            }

            // ---- audit_log (legacy format, without hidden fields) ----
            $newRow = (array) $conn->table('employee_payroll')->where('idno', $idno)->first();
            $oldAudit = array_diff_key($oldRow, array_flip(self::HIDDEN_FIELDS));
            $newAudit = array_diff_key($newRow, array_flip(self::HIDDEN_FIELDS));

            $fields = [];
            foreach ($newAudit as $field => $newValue) {
                $oldValue = $oldAudit[$field] ?? null;
                if ((string) $oldValue !== (string) $newValue) {
                    $fields[] = "$field: '$oldValue' → '$newValue'";
                }
            }
            if ($accountChange) {
                // Never the digits, only that it happened
                $fields[] = "account_number: $accountChange";
            }

            $conn->table('audit_log')->insert([
                'user'           => $actor,
                'employee_id'    => $idno,
                'link'           => mb_substr($link, 0, 255),
                'action'         => 'UPDATE',
                'ip_address'     => $ip ?? 'UNKNOWN',
                // Default (escaped) JSON like the legacy page: audit_log is latin1, so "→" must be stored as →
                'old_values'     => json_encode($oldAudit),
                'new_values'     => json_encode($newAudit),
                'changed_fields' => json_encode($fields),
                'occurred_at'    => $now,
            ]);

            return ['changed' => true, 'fields' => $fields];
        });
    }

    /**
     * Same storage format as the legacy portal: raw IV followed by raw ciphertext.
     */
    public function encrypt(string $plain): string
    {
        [$method, $key] = $this->cipher();

        $iv = random_bytes(openssl_cipher_iv_length($method));
        $cipher = openssl_encrypt($plain, $method, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Account number could not be encrypted.');
        }

        $stored = $iv . $cipher;

        // Refuse to store anything HRIS / the dashboard could not read back
        if ($this->decrypt($stored) !== $plain) {
            throw new RuntimeException('Account number encryption check failed.');
        }

        return $stored;
    }

    public function decrypt(?string $stored): ?string
    {
        if (empty($stored)) {
            return null;
        }

        [$method, $key] = $this->cipher();
        $ivLength = openssl_cipher_iv_length($method);
        $plain = openssl_decrypt(substr($stored, $ivLength), $method, $key, OPENSSL_RAW_DATA, substr($stored, 0, $ivLength));

        // A wrong key can still "decrypt" into random bytes, so only accept readable text
        return $plain !== false && preg_match('/^[\x20-\x7E]+$/', $plain) ? $plain : null;
    }

    /**
     * "•••• 1234" for display; never the full number.
     */
    public function masked(?string $stored): ?string
    {
        if (empty($stored)) {
            return null;
        }

        $plain = $this->decrypt($stored);

        return $plain === null ? 'Registered (cannot be read)' : '•••• ' . substr(preg_replace('/\D/', '', $plain), -4);
    }

    private function cipher(): array
    {
        $method = (string) config('payroll.bank_encryption_method');
        $key = (string) config('payroll.bank_encryption_key');

        if ($method === '' || $key === '' || !in_array(strtolower($method), openssl_get_cipher_methods(), true)) {
            throw new RuntimeException('Bank encryption settings are missing. Check BANK_ENCRYPTION_METHOD / BANK_ENCRYPTION_KEY.');
        }

        return [$method, $key];
    }

    // Amounts compared to the centavo (blank / NULL counts as 0, as payroll reads it); everything else as text
    private function same($a, $b): bool
    {
        $blank = fn ($v) => $v === null || $v === '';

        if (($blank($a) || is_numeric($a)) && ($blank($b) || is_numeric($b))) {
            return round((float) $a, 2) === round((float) $b, 2);
        }

        return (string) $a === (string) $b;
    }

    private function dateOf($value): ?string
    {
        return $value && !in_array($value, ['0000-00-00', '0001-01-01'], true) ? date('Y-m-d', strtotime($value)) : null;
    }
}
