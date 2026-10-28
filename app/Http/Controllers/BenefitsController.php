<?php

namespace App\Http\Controllers;

use App\Services\EmployeeBenefitsService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Salary & Benefits (legacy accounting managebenefits / editbenefits / audit_log):
 * the salary rate, salary type, benefit amounts and bank details payroll reads
 * from hris.employee_payroll and hris.employee_bank_info. Saving goes through
 * EmployeeBenefitsService; this controller validates, shows and redirects.
 */
class BenefitsController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    private const PER_PAGE = 50;

    private const HISTORY_PER_PAGE = 25;

    // Shift start times the legacy list shades gray (graveyard)
    private const GRAVEYARD_STARTS = ['23:00', '00:00', '01:00'];

    public function __construct(private EmployeeBenefitsService $benefits)
    {
    }

    public function index(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $companies = $this->companies();
        $codes = $companies->pluck('companycode')->all();

        $v = $request->validate([
            'search'     => ['nullable', 'string', 'max:100'],
            'company'    => ['nullable', Rule::in($codes)],
            'department' => ['nullable', 'integer'],
        ]);
        $search = trim((string) ($v['search'] ?? ''));

        $base = $this->employees($search, $codes);

        $counts = (clone $base)->selectRaw('ed.company, COUNT(*) AS n')->groupBy('ed.company')->pluck('n', 'company');

        // While searching, open the first company that has matches
        $company = $v['company'] ?? $companies->first()->companycode ?? null;
        if ($search !== '' && (int) ($counts[$company] ?? 0) === 0) {
            $company = collect($codes)->first(fn ($c) => (int) ($counts[$c] ?? 0) > 0) ?? $company;
        }

        $departments = (clone $base)
            ->where('ed.company', $company)
            ->join('department as d', 'd.id', '=', 'ed.department')
            ->selectRaw('d.id, d.department, COUNT(*) AS n')
            ->groupBy('d.id', 'd.department')
            ->orderBy('d.department')
            ->get();
        $department = $departments->contains('id', $v['department'] ?? null) ? (int) $v['department'] : null;

        $inCompany = (clone $base)->where('ed.company', $company);

        $stats = (clone $inCompany)
            ->leftJoin('employee_bank_info as ebi', 'ebi.idno', '=', 'ep.idno')
            ->selectRaw("COUNT(*) AS total,
                SUM(COALESCE(pay.salary_type, 'Rated') = 'Rated') AS rated,
                SUM(pay.salary_type IN ('Fixed', 'Daily')) AS fixed,
                SUM(COALESCE(pay.salary, 0) <= 0) AS no_salary,
                SUM(COALESCE(pay.bank, '') <> 'Cash' AND (ebi.account_number IS NULL OR LENGTH(ebi.account_number) = 0)) AS no_account")
            ->first();

        $rows = (clone $inCompany)
            ->when($department, fn ($q) => $q->where('ed.department', $department))
            ->select(
                'ep.idno', 'ep.lastname', 'ep.firstname', 'ep.suffix', 'ed.dateofhired', 'ed.startshift',
                'pay.sss', 'pay.phic', 'pay.hdmf', 'pay.salary', 'pay.salary_type', 'pay.bank'
            )
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->orderBy('ep.idno')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('benefits.index', [
            'rows'        => $rows,
            'companies'   => $companies->map(fn ($c) => ['code' => $c->companycode, 'name' => $c->companyname, 'count' => (int) ($counts[$c->companycode] ?? 0)]),
            'company'     => $company,
            'departments' => $departments,
            'department'  => $department,
            'search'      => $search,
            'stats'       => $stats,
            'graveyard'   => self::GRAVEYARD_STARTS,
            'listContext' => array_filter(['search' => $search, 'company' => $company, 'department' => $department, 'page' => $rows->currentPage() > 1 ? $rows->currentPage() : null]),
        ]);
    }

    public function edit(Request $request, string $idno): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $emp = $this->employee($idno);

        $pay = $conn->table('employee_payroll')
            ->where('idno', $idno)
            ->first(['sss', 'phic', 'hdmf', 'philcare', 'generali', 'fwd', 'tax', 'otherbenefits', 'salary', 'salary_type',
                'effective_date', 'previous_salary', 'bank', 'updatedby', 'updateddatetime', 'addedby', 'addeddatetime']);
        $bank = $conn->table('employee_bank_info')->where('idno', $idno)->first(['bank_name', 'account_number']);

        // Previous / next in the same company and department, by last name (legacy order)
        $order = $conn->table('employee_details as ed')
            ->join('employee_profile as ep', 'ep.idno', '=', 'ed.idno')
            ->where('ed.company', $emp->company)
            ->where('ed.department', $emp->department)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->orderBy('ep.idno')
            ->get(['ep.idno', 'ep.lastname', 'ep.firstname']);
        $pos = $order->search(fn ($r) => (string) $r->idno === $idno);
        $neighbour = fn ($r) => $r ? ['idno' => $r->idno, 'name' => trim($r->lastname . ', ' . $r->firstname)] : null;

        $context = array_filter([
            'search'     => (string) $request->query('search', ''),
            'company'    => $emp->company,
            'department' => (int) $emp->department ?: null,
        ]);

        return view('benefits.edit', [
            'emp'      => $emp,
            'pay'      => $pay,
            'bankName' => $bank->bank_name ?? ($pay->bank ?? ''),
            'account'  => $this->benefits->masked($bank->account_number ?? null),
            'prev'     => $pos !== false ? $neighbour($order[$pos - 1] ?? null) : null,
            'next'     => $pos !== false ? $neighbour($order[$pos + 1] ?? null) : null,
            'position' => $pos !== false ? ['at' => $pos + 1, 'of' => $order->count()] : null,
            'history'  => $this->historyEntries($conn->table('audit_log as a')->where('a.employee_id', $idno)->orderByDesc('a.occurred_at')->orderByDesc('a.id')->limit(5)->get()),
            'context'  => $context,
            'listUrl'  => route('payroll.benefits.index', $context),
        ]);
    }

    public function update(Request $request, string $idno): RedirectResponse
    {
        $emp = $this->employee($idno);

        $amount = ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $rules = [
            'salary'         => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'salary_type'    => ['required', Rule::in(array_keys(EmployeeBenefitsService::SALARY_TYPES))],
            'effective_date' => ['nullable', 'date_format:Y-m-d'],
            'bank'           => ['nullable', Rule::in(EmployeeBenefitsService::BANKS)],
            'account_action' => ['required', Rule::in(['keep', 'change', 'remove'])],
            'account_number' => ['exclude_unless:account_action,change', 'required', 'regex:/^[0-9][0-9 -]{4,38}[0-9]$/'],
        ];
        foreach (EmployeeBenefitsService::AMOUNT_FIELDS as $f) {
            $rules[$f] = $amount;
        }

        // Amounts may be typed with thousands separators
        $request->merge(collect(array_merge(EmployeeBenefitsService::AMOUNT_FIELDS, ['salary']))
            ->mapWithKeys(fn ($f) => [$f => str_replace([',', ' '], '', (string) $request->input($f, ''))])
            ->all());

        // Back to the form without flashing the typed account number into the session
        $fail = fn (ValidationException $e) => back()->withErrors($e->errors())->withInput($request->except('account_number'));

        try {
            $v = Validator::make($request->all(), $rules, [
                'regex'                   => 'Enter an amount like 1234.56 (no negative numbers, up to 2 decimals).',
                'account_number.required' => 'Enter the new account number, or choose Keep.',
                'account_number.regex'    => 'Account numbers can only contain digits, spaces and dashes.',
            ])->validate();
        } catch (ValidationException $e) {
            return $fail($e);
        }

        $input = [
            'salary'         => (float) $v['salary'],
            'salary_type'    => $v['salary_type'],
            'effective_date' => $v['effective_date'] ?? null,
            'bank'           => $v['bank'] ?? '',
            'account_action' => $v['account_action'],
            'account_number' => isset($v['account_number']) ? trim($v['account_number']) : null,
        ];
        foreach (EmployeeBenefitsService::AMOUNT_FIELDS as $f) {
            $input[$f] = (float) ($v[$f] ?? 0);
        }

        $actor = $this->actorName();
        abort_if($actor === null, 403, 'Your HRIS account could not be identified. Log in again from HRIS.');

        try {
            $result = $this->benefits->save($idno, $input, $actor, '/' . $request->path(), $request->ip());
        } catch (ValidationException $e) {
            return $fail($e);
        } catch (RuntimeException $e) {
            report($e);

            // Database errors carry SQL and employee data: never shown, only logged
            $message = $e instanceof QueryException
                ? 'A database error occurred. Please try again or contact the administrator.'
                : $e->getMessage();

            return back()->withInput($request->except('account_number'))
                ->with('benefits_error', 'Nothing was saved. ' . $message);
        }

        $back = redirect()->route('payroll.benefits.edit', ['idno' => $idno] + array_filter(['search' => $request->query('search')]));

        return $result['changed']
            ? $back->with('benefits_saved', count($result['fields']))
            : $back->with('benefits_unchanged', true);
    }

    /**
     * Change history (legacy audit_log.php), newest first, with filters.
     */
    public function history(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $v = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'user'   => ['nullable', 'string', 'max:150'],
            'from'   => ['nullable', 'date_format:Y-m-d'],
            'to'     => ['nullable', 'date_format:Y-m-d'],
        ]);
        $search = trim((string) ($v['search'] ?? ''));

        $entries = $conn->table('audit_log as a')
            ->leftJoin('employee_profile as e', 'e.idno', '=', 'a.employee_id')
            ->when($search !== '', function ($q) use ($search) {
                $s = '%' . addcslashes($search, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('a.employee_id', 'like', $s)
                    ->orWhereRaw("CONCAT_WS(' ', e.lastname, e.firstname) LIKE ?", [$s])
                    ->orWhereRaw("CONCAT_WS(' ', e.firstname, e.lastname) LIKE ?", [$s]));
            })
            ->when($v['user'] ?? null, fn ($q, $u) => $q->where('a.user', $u))
            ->when($v['from'] ?? null, fn ($q, $d) => $q->where('a.occurred_at', '>=', $d . ' 00:00:00'))
            ->when($v['to'] ?? null, fn ($q, $d) => $q->where('a.occurred_at', '<=', $d . ' 23:59:59'))
            ->select('a.*', 'e.firstname', 'e.lastname')
            ->orderByDesc('a.occurred_at')
            ->orderByDesc('a.id')
            ->paginate(self::HISTORY_PER_PAGE)
            ->withQueryString();

        $entries->setCollection(collect($this->historyEntries($entries->getCollection())));

        return view('benefits.history', [
            'entries' => $entries,
            'users'   => $conn->table('audit_log')->whereNotNull('user')->where('user', '!=', '')->distinct()->orderBy('user')->pluck('user'),
            'filters' => ['search' => $search] + array_intersect_key($v, array_flip(['user', 'from', 'to'])),
        ]);
    }

    /**
     * audit_log rows as the pages show them: field changes parsed from
     * "field: 'old' → 'new'", snapshots without hidden fields (ga_secret).
     */
    private function historyEntries($rows): array
    {
        $hidden = array_flip(EmployeeBenefitsService::HIDDEN_FIELDS);
        $bookkeeping = ['updatedby', 'updateddatetime', 'addedby', 'addeddatetime', 'id', 'idno'];

        return collect($rows)->map(function ($r) use ($hidden, $bookkeeping) {
            $changes = collect(json_decode((string) $r->changed_fields, true) ?: [])
                ->map(function ($line) {
                    if (preg_match("/^(\w+): '(.*)' → '(.*)'$/su", (string) $line, $m)) {
                        return ['field' => $m[1], 'old' => $m[2], 'new' => $m[3]];
                    }
                    if (preg_match('/^account_number: (\w+)$/', (string) $line, $m)) {
                        return ['field' => 'account_number', 'old' => null, 'new' => $m[1]];
                    }

                    return ['field' => null, 'old' => null, 'new' => (string) $line];
                })
                ->reject(fn ($c) => isset($hidden[$c['field']]))
                ->values();

            $snapshot = fn ($json) => array_diff_key(json_decode((string) $json, true) ?: [], $hidden);

            return (object) [
                'id'       => $r->id,
                'user'     => $r->user,
                'idno'     => $r->employee_id,
                'employee' => isset($r->lastname) ? trim($r->lastname . ', ' . $r->firstname, ', ') : null,
                'when'     => $r->occurred_at ? date('M d, Y h:i A', strtotime($r->occurred_at)) : null,
                'ip'       => $r->ip_address,
                'source'   => str_starts_with((string) $r->link, '/accounting/') ? 'Legacy portal' : 'Accounting app',
                'changes'  => $changes->reject(fn ($c) => in_array($c['field'], $bookkeeping, true))->values(),
                'old'      => $snapshot($r->old_values),
                'new'      => $snapshot($r->new_values),
            ];
        })->all();
    }

    private function companies()
    {
        return DB::connection(self::HRIS_CONNECTION)->table('settings')
            ->select('companycode', 'companyname')
            ->orderBy('companycode')
            ->get();
    }

    /**
     * Active employees (legacy list skips resigned) with their payroll row.
     */
    private function employees(string $search, array $codes): Builder
    {
        return DB::connection(self::HRIS_CONNECTION)->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->leftJoin('employee_payroll as pay', 'pay.idno', '=', 'ep.idno')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $codes)
            ->when($search !== '', function ($q) use ($search) {
                $s = '%' . addcslashes($search, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('ep.idno', 'like', $s)
                    ->orWhereRaw("CONCAT_WS(' ', ep.lastname, ep.firstname) LIKE ?", [$s])
                    ->orWhereRaw("CONCAT_WS(' ', ep.firstname, ep.lastname) LIKE ?", [$s])
                    ->orWhereRaw("CONCAT(ep.lastname, ', ', ep.firstname) LIKE ?", [$s]));
            });
    }

    /**
     * One active employee in a known company, or 404.
     */
    private function employee(string $idno): object
    {
        $emp = DB::connection(self::HRIS_CONNECTION)->table('employee_details as ed')
            ->join('employee_profile as ep', 'ep.idno', '=', 'ed.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->leftJoin('jobtitle as jt', 'jt.id', '=', 'ed.designation')
            ->leftJoin('settings as s', 's.companycode', '=', 'ed.company')
            ->where('ed.idno', $idno)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $this->companies()->pluck('companycode'))
            ->orderByDesc('ed.id')
            ->first([
                'ep.idno', 'ep.lastname', 'ep.firstname', 'ep.suffix', 'ed.company', 'ed.department', 'ed.status',
                'ed.dateofhired', 'ed.startshift', 'ed.endshift', 'd.department as department_name', 'jt.jobtitle', 's.companyname',
            ]);
        abort_if(!$emp, 404);

        return $emp;
    }

    // Legacy addedby / updatedby / audit user: the accounting user's full name
    private function actorName(): ?string
    {
        $idno = trim((string) session('portal_idno'));
        if ($idno === '') {
            return null;
        }

        $p = DB::connection(self::HRIS_CONNECTION)->table('employee_profile')->where('idno', $idno)->first(['firstname', 'lastname']);

        return $p ? (trim($p->firstname . ' ' . $p->lastname) ?: null) : null;
    }
}
