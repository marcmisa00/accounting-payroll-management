<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** DB connection that holds the payroll / employee tables */
    private const CONN = 'hris';

    /** Companies shown in the yearly graphs (must match employee_details.company) */
    public const COMPANIES = ['NESI1', 'NESI2', 'NEWIND'];

    /** Value used in URLs/queries for employees with no bank */
    public const NO_BANK = '__none__';

    /**
     * Dashboard: total net pay per company, then per bank, for one payroll period.
     */
    public function index(Request $request)
    {
        $periods   = $this->periods();
        $currentId = $this->currentPeriodId($periods);
        $periodId  = $request->query('period', $currentId);
        $period    = $periods->firstWhere('id', $periodId) ?? $periods->firstWhere('id', $currentId);

        // Years that have payroll periods, and the year the graphs should show
        $years = $periods
            ->map(fn ($p) => (int) date('Y', strtotime($p->periodto)))
            ->unique()->sortDesc()->values();

        $year = (int) $request->query('year', $period ? date('Y', strtotime($period->periodto)) : now()->year);
        if ($years->isNotEmpty() && !$years->contains($year)) {
            $year = $years->first();
        }

        $chart = $this->yearlyChart($periods, $year);

        $companies = collect();

        if ($period) {
            $rows = $this->employeeNetPayQuery($period->id)
                ->selectRaw("ed.company AS company")
                ->selectRaw("COALESCE(NULLIF(TRIM(epy.bank), ''), '" . self::NO_BANK . "') AS bank")
                ->selectRaw('COUNT(DISTINCT ep.idno) AS employees')
                ->selectRaw('SUM(COALESCE(pd.totalpay, 0) + COALESCE(ad.amount, 0) - COALESCE(de.amount, 0)) AS net_pay')
                ->groupBy('ed.company', 'bank')
                ->orderBy('ed.company')
                ->orderBy('bank')
                ->get();

            // company => [ total, employees, banks[] ]
            $companies = $rows->groupBy('company')->map(fn ($banks) => (object) [
                'banks'     => $banks,
                'employees' => $banks->sum('employees'),
                'net_pay'   => $banks->sum('net_pay'),
            ]);
        }

        return view('dashboard', [
            'periods'   => $periods,
            'currentId' => $currentId,
            'period'    => $period,
            'companies' => $companies,
            'grandTotal' => $companies->sum('net_pay'),
            'years'     => $years,
            'year'      => $year,
            'chart'     => $chart,
        ]);
    }

    /**
     * Full page: every employee for one company + bank + period.
     */
    public function detail(Request $request)
    {
        $data = $request->validate([
            'period'  => ['required', 'integer'],
            'company' => ['required', 'string'],
            'bank'    => ['required', 'string'],
        ]);

        $period = DB::connection(self::CONN)->table('payroll')
            ->where('id', $data['period'])
            ->first();

        abort_if(!$period, 404, 'Payroll period not found.');

        $query = $this->employeeNetPayQuery($period->id)
            ->where('ed.company', $data['company']);

        if ($data['bank'] === self::NO_BANK) {
            $query->whereRaw("(epy.bank IS NULL OR TRIM(epy.bank) = '')");
        } else {
            $query->where('epy.bank', $data['bank']);
        }

        $employees = $query
            ->select('ep.idno', 'ep.lastname', 'ep.firstname', 'ep.suffix', 'd.department', 'ebi.account_number')
            ->selectRaw('(COALESCE(pd.totalpay, 0) + COALESCE(ad.amount, 0) - COALESCE(de.amount, 0)) AS net_pay')
            ->orderBy('d.id')
            ->orderBy('ep.lastname')
            ->get()
            ->map(function ($e) {
                $e->account_number = $this->displayAccountNumber($e->account_number);
                return $e;
            });

        return view('dashboard-detail', [
            'period'    => $period,
            'company'   => $data['company'],
            'bank'      => $data['bank'],
            'bankLabel' => $data['bank'] === self::NO_BANK ? 'No bank info' : $data['bank'],
            'noBank'    => $data['bank'] === self::NO_BANK,
            'employees' => $employees,
            'total'     => $employees->sum('net_pay'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Base query: active employees joined with their totals for one period.
     * Uses one grouped subquery per table instead of a query per employee.
     */
    private function employeeNetPayQuery(int|string $periodId)
    {
        $db = DB::connection(self::CONN);

        $pd = $db->table('payroll_details')
            ->select('idno')->selectRaw('SUM(totalpay) AS totalpay')
            ->where('payrollperiod', $periodId)->groupBy('idno');

        $de = $db->table('payroll_deductions')
            ->select('idno')->selectRaw('SUM(amount) AS amount')
            ->where('payrollperiod', $periodId)->groupBy('idno');

        $ad = $db->table('payroll_addons')
            ->select('idno')->selectRaw('SUM(amount) AS amount')
            ->where('payrollperiod', $periodId)->groupBy('idno');

        return $db->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->leftJoin('employee_payroll as epy', 'epy.idno', '=', 'ep.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->leftJoin('employee_bank_info as ebi', 'ebi.idno', '=', 'ep.idno')
            ->leftJoinSub($pd, 'pd', 'pd.idno', '=', 'ep.idno')
            ->leftJoinSub($de, 'de', 'de.idno', '=', 'ep.idno')
            ->leftJoinSub($ad, 'ad', 'ad.idno', '=', 'ep.idno')
            ->where('ed.status', 'not like', '%RESIGNED%');
    }

    /**
     * Monthly net pay (Jan-Dec) for each company in one year.
     * A period counts toward the month its "period to" date falls in.
     * Net pay = pay + add-ons - deductions.
     */
    private function yearlyChart($periods, int $year): array
    {
        $monthById = $periods
            ->filter(fn ($p) => (int) date('Y', strtotime($p->periodto)) === $year)
            ->mapWithKeys(fn ($p) => [$p->id => (int) date('n', strtotime($p->periodto))]);

        $data = [];
        foreach (self::COMPANIES as $company) {
            $data[$company] = array_fill(1, 12, 0.0);
        }

        if ($monthById->isNotEmpty()) {
            $ids = $monthById->keys()->all();

            $sources = [
                ['payroll_details',    'totalpay',  1],
                ['payroll_addons',     'amount',    1],
                ['payroll_deductions', 'amount',   -1],
            ];

            foreach ($sources as [$table, $column, $sign]) {
                $rows = DB::connection(self::CONN)->table("$table as t")
                    ->join('employee_details as ed', 'ed.idno', '=', 't.idno')
                    ->whereIn('t.payrollperiod', $ids)
                    ->whereIn('ed.company', self::COMPANIES)
                    ->groupBy('ed.company', 't.payrollperiod')
                    ->select('ed.company', 't.payrollperiod')
                    ->selectRaw("SUM(t.$column) AS amount")
                    ->get();

                foreach ($rows as $r) {
                    $company = strtoupper(trim($r->company));
                    $month   = $monthById[$r->payrollperiod] ?? null;

                    if ($month && isset($data[$company])) {
                        $data[$company][$month] += $sign * (float) $r->amount;
                    }
                }
            }
        }

        $series = [];
        $totals = [];
        foreach ($data as $company => $months) {
            $series[$company] = array_map(fn ($v) => round($v, 2), array_values($months));
            $totals[$company] = round(array_sum($months), 2);
        }

        return [
            'series'  => $series,
            'totals'  => $totals,
            'overall' => round(array_sum($totals), 2),
        ];
    }

    private function periods()
    {
        return DB::connection(self::CONN)->table('payroll')
            ->select('id', 'periodfrom', 'periodto')
            ->orderByDesc('periodfrom')
            ->get();
    }

    /**
     * The period that contains today; otherwise the most recent one that has ended;
     * otherwise the newest period.
     */
    private function currentPeriodId($periods)
    {
        $today = now()->toDateString();

        $current = $periods->first(fn ($p) => $p->periodfrom <= $today && $p->periodto >= $today)
            ?? $periods->first(fn ($p) => $p->periodto <= $today)
            ?? $periods->first();

        return $current?->id;
    }

    private function decryptAccountNumber(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return '';
        }

        $method = config('payroll.bank_encryption_method');
        $key    = config('payroll.bank_encryption_key');

        // Missing or invalid settings: show a marker instead of crashing the page
        if (empty($method) || empty($key) || !in_array(strtolower($method), openssl_get_cipher_methods(), true)) {
            logger()->error('Bank encryption settings are missing or invalid. Check BANK_ENCRYPTION_METHOD / BANK_ENCRYPTION_KEY in .env, then run php artisan config:clear.');
            return null;
        }

        try {
            $ivLength   = openssl_cipher_iv_length($method);
            $iv         = substr($encrypted, 0, $ivLength);
            $ciphertext = substr($encrypted, $ivLength);

            $plain = openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
        } catch (\Throwable $e) {
            logger()->error('Account number decrypt failed: ' . $e->getMessage());
            return null;
        }

        // A wrong key can still "decrypt" into random bytes, so only accept readable text
        if ($plain === false || !preg_match('/^[\x20-\x7E]+$/', $plain)) {
            return null;
        }

        return $plain;
    }

    /**
     * What the page shows: last 4 digits only, or "Registered" if it can't be decrypted.
     */
    private function displayAccountNumber(?string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }

        $plain = $this->decryptAccountNumber($encrypted);

        if ($plain === null) {
            return 'Registered';
        }

        $length = strlen($plain);

        return $length > 4
            ? str_repeat('•', $length - 4) . substr($plain, -4)
            : $plain;
    }
}