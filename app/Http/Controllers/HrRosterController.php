<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only HR roster: active employees by company → department, with the
 * a month-day date filter (Date Hired / Regularization / Fulltime, any year),
 * and a view-only employee profile.
 * Nothing here writes to the database.
 */
class HrRosterController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    private const PER_PAGE = 50;

    // Shift start times the legacy roster treats as graveyard
    private const GRAVEYARD_STARTS = ['23:00', '00:00', '01:00'];

    // employee_details date columns the roster can filter on, with their labels
    public const DATE_FIELDS = [
        'dateofhired'    => 'Date Hired',
        'dateofregular'  => 'Regularization',
        'dateoffulltime' => 'Fulltime',
    ];

    public function index(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $companies = $this->companies();
        $ctx = $this->context($request, $companies->pluck('companycode')->all());

        $base = $this->rosterQuery($ctx, $companies->pluck('companycode')->all());

        $companyCounts = (clone $base)
            ->selectRaw('ed.company, COUNT(*) AS n')
            ->groupBy('ed.company')
            ->pluck('n', 'company');

        // While searching, jump to the first company that has matches
        $company = $ctx['company'] ?? $companies->first()->companycode ?? null;
        if ($ctx['searching'] && (int) ($companyCounts[$company] ?? 0) === 0) {
            $company = $companies->pluck('companycode')->first(fn ($c) => (int) ($companyCounts[$c] ?? 0) > 0) ?? $company;
        }

        $companyTabs = $companies->map(fn ($c) => [
            'code'    => $c->companycode,
            'name'    => $c->companyname,
            'matches' => (int) ($companyCounts[$c->companycode] ?? 0),
        ]);

        $deptCounts = (clone $base)
            ->where('ed.company', $company)
            ->selectRaw('ed.department AS id, COUNT(*) AS n')
            ->groupBy('ed.department')
            ->pluck('n', 'id');

        $departments = $conn->table('employee_details as ed')
            ->join('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.company', $company)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->select('d.id', 'd.department')
            ->distinct()
            ->orderBy('d.department')
            ->get()
            ->map(fn ($d) => [
                'id'      => (int) $d->id,
                'name'    => $d->department,
                'matches' => (int) ($deptCounts[$d->id] ?? 0),
            ]);

        $department = $departments->contains('id', $ctx['department']) ? $ctx['department'] : null;

        $employees = $this->withRowColumns((clone $base))
            ->where('ed.company', $company)
            ->when($department, fn ($q) => $q->where('ed.department', $department))
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->orderBy('ep.idno')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn ($e) => $this->presentRow($e));

        return view('hr.roster', [
            'employees'   => $employees,
            'companyTabs' => $companyTabs,
            'company'     => $company,
            'departments' => $departments,
            'department'  => $department,
            'deptTotal'   => (int) ($companyCounts[$company] ?? 0),
            'search'      => $ctx['search'],
            'date'        => $ctx['date'],
            'searching'   => $ctx['searching'],
            'listContext' => $this->listContext($ctx, $company, $department, $employees->currentPage()),
            'graveyard'   => self::GRAVEYARD_STARTS,
        ]);
    }

    public function show(Request $request, string $idno): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $companies = $this->companies();
        $codes = $companies->pluck('companycode')->all();

        $det = $conn->table('employee_details')
            ->where('idno', $idno)
            ->where('status', 'not like', '%RESIGNED%')
            ->whereIn('company', $codes)
            ->orderByDesc('id')
            ->first();
        abort_if(!$det, 404);

        $p = $conn->table('employee_profile')
            ->where('idno', $idno)
            ->select(
                'id', 'idno', 'lastname', 'firstname', 'middlename', 'suffix', 'birthdate', 'civilstatus', 'sex',
                'address', 'phone_number', 'comp_email', 'contactperson', 'contactnumber', 'relationship'
            )
            ->first();
        abort_if(!$p, 404);

        // Some HRIS tables hold more than one row per employee; use the latest
        $latest = fn (string $table, array $cols) => $conn->table($table)
            ->where('idno', $idno)
            ->orderByRaw('COALESCE(updateddatetime, addeddatetime) DESC')
            ->first($cols);

        $referral = $conn->table('employee_referral as er')
            ->leftJoin('employee_profile as ref', 'ref.idno', '=', 'er.referredby')
            ->where('er.idno', $idno)
            ->orderByDesc('er.effectivity')
            ->first(['er.referredby', 'er.referral_type', 'er.effectivity', 'ref.firstname', 'ref.lastname']);
        if ($referral && !$this->hasReferral($referral->referredby, $referral->effectivity)) {
            $referral = null;
        }

        $now = now('Asia/Manila');
        $year = (int) $now->year;

        $pointsRows = $conn->table('points')
            ->where('idno', $idno)
            ->whereYear('logindate', $year)
            ->orderBy('logindate')
            ->get(['logindate', 'points', 'offense']);

        $hired = $this->validDate($det->dateofhired);
        $service = $hired ? Carbon::parse($hired, 'Asia/Manila')->diff($now) : null;
        $birthdate = $this->validDate($p->birthdate);

        $leave = $conn->table('leave_credits')->where('idno', $idno)->first();

        $d = [
            'profile'     => $p,
            'details'     => $det,
            'fullName'    => $this->displayName($p),
            'initials'    => strtoupper(mb_substr((string) $p->firstname, 0, 1) . mb_substr((string) $p->lastname, 0, 1)),
            'jobtitle'    => $conn->table('jobtitle')->where('id', $det->designation)->value('jobtitle'),
            'department'  => $conn->table('department')->where('id', $det->department)->value('department'),
            'companyname' => $companies->firstWhere('companycode', $det->company)->companyname ?? $det->company,
            'years'       => $service->y ?? 0,
            'months'      => $service->m ?? 0,
            'days'        => $service->d ?? 0,
            'age'         => $birthdate ? Carbon::parse($birthdate)->age : null,
            'shift'       => $this->shiftLabel($det->startshift, $det->endshift),
            'isGraveyard' => $this->isGraveyard($det->startshift),
            'leave'       => $leave,
            'periodFrom'  => $this->validDate($leave->periodfrom ?? null),
            'periodTo'    => $this->validDate($leave->periodto ?? null),
            'benefits'    => $latest('employee_benefits', ['insurance', 'hmo', 'sss', 'tin', 'phic', 'hdmf']),
            'contract'    => $latest('employee_contract', ['regular', 'regulardate', 'probationary', 'probationarydate']),
            'checklist'   => $latest('employee_checklist', ['dateoriented', 'releasedtempid', 'releasedpermanentid', 'status']),
            'referral'    => $referral,
            'currentYear' => $year,
            'pointsRows'  => $pointsRows,
            'points'      => (float) $pointsRows->sum('points'),
        ];

        // Previous / next follow the list the user came from (same filters and order)
        $ctx = $this->context($request, $codes);
        $company = $ctx['company'] ?? $det->company;
        $order = $this->rosterQuery($ctx, $codes)
            ->where('ed.company', $company)
            ->when($ctx['department'], fn ($q) => $q->where('ed.department', $ctx['department']))
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->orderBy('ep.idno')
            ->get(['ep.idno', 'ep.lastname', 'ep.firstname']);

        $pager = null;
        $pos = $order->search(fn ($r) => (string) $r->idno === (string) $idno);
        if ($pos !== false) {
            $neighbour = fn ($r) => $r ? ['idno' => $r->idno, 'name' => trim($r->lastname . ', ' . $r->firstname)] : null;
            $pager = [
                'position' => $pos + 1,
                'total'    => $order->count(),
                'prev'     => $neighbour($order[$pos - 1] ?? null),
                'next'     => $neighbour($order[$pos + 1] ?? null),
            ];
        }

        return view('hr.profile', [
            'd'           => $d,
            'idno'        => $idno,
            'pager'       => $pager,
            'listContext' => $this->listContext($ctx, $company, $ctx['department'], $ctx['page']),
            'eoMonth'     => strtolower($now->format('M')),
        ]);
    }

    private function companies()
    {
        return DB::connection(self::HRIS_CONNECTION)->table('settings')
            ->select('companycode', 'companyname')
            ->orderBy('companycode')
            ->get();
    }

    /**
     * Search, date filter, company, department and page from the query string.
     * Old links with the removed column filters (f[...]) are ignored.
     */
    private function context(Request $request, array $companyCodes): array
    {
        $v = $request->validate([
            'search'     => ['nullable', 'string', 'max:100'],
            'company'    => ['nullable', 'in:' . implode(',', $companyCodes)],
            'department' => ['nullable', 'integer'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'date_field' => ['nullable', 'in:' . implode(',', array_keys(self::DATE_FIELDS))],
            'from_month' => ['nullable', 'integer', 'between:1,12'],
            'from_day'   => ['nullable', 'integer', 'between:1,31'],
            'to_month'   => ['nullable', 'integer', 'between:1,12'],
            'to_day'     => ['nullable', 'integer', 'between:1,31'],
        ]);

        // Month and day only (any year). With one side given, that month / day alone.
        $int = fn ($x) => empty($x) ? null : (int) $x;
        [$fm, $fd, $tm, $td] = [$int($v['from_month'] ?? null), $int($v['from_day'] ?? null),
            $int($v['to_month'] ?? null), $int($v['to_day'] ?? null)];
        $date = null;
        if ($fm || $tm) {
            if (!$fm) {
                [$fm, $fd] = [$tm, $td];
            }
            if (!$tm) {
                [$tm, $td] = [$fm, $fd];
            }
            $date = [
                'date_field' => $v['date_field'] ?? array_key_first(self::DATE_FIELDS),
                'from_month' => $fm,
                'from_day'   => $fd,
                'to_month'   => $tm,
                'to_day'     => $td,
            ];
        }

        $search = trim((string) ($v['search'] ?? ''));

        return [
            'search'     => $search,
            'date'       => $date,
            'company'    => $v['company'] ?? null,
            'department' => isset($v['department']) ? (int) $v['department'] : null,
            'page'       => (int) ($v['page'] ?? 1),
            'searching'  => $search !== '' || $date !== null,
        ];
    }

    /**
     * Active employees matching search and date filter, across all companies.
     */
    private function rosterQuery(array $ctx, array $companyCodes): Builder
    {
        $like = fn (string $v) => '%' . addcslashes($v, '%_\\') . '%';

        $q = DB::connection(self::HRIS_CONNECTION)->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->leftJoin('jobtitle as jt', 'jt.id', '=', 'ed.designation')
            ->leftJoin('employee_payroll as pay', 'pay.idno', '=', 'ep.idno')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companyCodes);

        if ($ctx['search'] !== '') {
            $s = $like($ctx['search']);
            $q->where(fn ($w) => $w
                ->where('ep.idno', 'like', $s)
                ->orWhereRaw("CONCAT_WS(' ', ep.lastname, ep.firstname, ep.middlename) LIKE ?", [$s])
                ->orWhereRaw("CONCAT_WS(' ', ep.firstname, ep.middlename, ep.lastname) LIKE ?", [$s])
                ->orWhereRaw("CONCAT(ep.lastname, ', ', ep.firstname) LIKE ?", [$s]));
        }

        if ($date = $ctx['date']) {
            // Column comes from the validated DATE_FIELDS list, never raw input
            $col = 'ed.' . $date['date_field'];
            $md = "DATE_FORMAT($col, '%m%d')";
            $from = sprintf('%02d%02d', $date['from_month'], $date['from_day'] ?? 1);
            $to = sprintf('%02d%02d', $date['to_month'], $date['to_day'] ?? 31);

            // Skip empty / placeholder dates (0000-00-00, 0001-01-01)
            $q->where($col, '>', '0001-01-01');
            if ($from <= $to) {
                $q->whereRaw("$md BETWEEN ? AND ?", [$from, $to]);
            } else {
                // Range across New Year, e.g. Dec 15 → Jan 15
                $q->where(fn ($w) => $w->whereRaw("$md >= ?", [$from])->orWhereRaw("$md <= ?", [$to]));
            }
        }

        return $q;
    }

    /**
     * Columns shown in the roster table (no salary or bank data).
     */
    private function withRowColumns(Builder $q): Builder
    {
        return $q->leftJoin('employee_referral as er', 'er.idno', '=', 'ep.idno')
            ->leftJoin('employee_profile as ref', 'ref.idno', '=', 'er.referredby')
            ->select(
                'ep.idno', 'ep.lastname', 'ep.firstname', 'ep.middlename', 'ep.suffix', 'ep.eligibility',
                'jt.jobtitle',
                'ed.status', 'ed.dateofhired', 'ed.dateofregular', 'ed.dateoffulltime',
                'ed.startshift', 'ed.endshift', 'ed.location', 'ed.work_area',
                'pay.salary_type',
                'er.referredby', 'er.referral_type', 'er.effectivity',
                'ref.firstname as ref_firstname', 'ref.lastname as ref_lastname'
            );
    }

    /**
     * Display values for one roster row, following the legacy roster's rules.
     */
    private function presentRow(object $e): object
    {
        $eligibility = trim((string) $e->eligibility);
        $e->eligibility_display = in_array(strtoupper($eligibility), ['', 'N/A', 'NA', '-'], true) ? '' : $eligibility;

        $e->has_referral = $this->hasReferral($e->referredby, $e->effectivity);
        $e->referrer_name = $e->has_referral ? trim($e->ref_firstname . ' ' . $e->ref_lastname) : '';
        $e->referral_fee = $e->has_referral
            ? date('M d, Y', strtotime($e->effectivity)) . ($e->referral_type ? " ({$e->referral_type})" : '')
            : '';

        $e->shift_start = $e->startshift && $e->endshift ? date('h:i A', strtotime($e->startshift)) : '';
        $e->shift_end = $e->startshift && $e->endshift ? date('h:i A', strtotime($e->endshift)) : '';

        $e->row_class = match (true) {
            in_array($e->salary_type, ['Fixed', 'Daily'], true) => 'hr-row-blue',
            $this->isGraveyard($e->startshift)                => 'hr-row-gray',
            default                                           => '',
        };

        return $e;
    }

    /**
     * Query string that brings the user back to the same roster view.
     */
    private function listContext(array $ctx, ?string $company, ?int $department, int $page): array
    {
        return array_filter(array_merge($ctx['date'] ?? [], [
            'search'     => $ctx['search'],
            'company'    => $company,
            'department' => $department,
            'page'       => $page > 1 ? $page : null,
        ]), fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    // Same rule as the legacy accounting roster
    private function hasReferral(?string $referredBy, ?string $effectivity): bool
    {
        return !in_array(trim((string) $referredBy), ['', 'N/A'], true)
            && $this->validDate($effectivity) !== null;
    }

    private function validDate(?string $date): ?string
    {
        return $date && !in_array($date, ['0000-00-00', '0001-01-01'], true) ? $date : null;
    }

    private function isGraveyard(?string $start): bool
    {
        return $start !== null && in_array(substr($start, 0, 5), self::GRAVEYARD_STARTS, true);
    }

    private function shiftLabel(?string $start, ?string $end): string
    {
        return $start && $end ? date('h:i A', strtotime($start)) . ' - ' . date('h:i A', strtotime($end)) : '';
    }

    private function displayName(object $p): string
    {
        $part = fn ($v) => in_array(strtoupper(trim((string) $v)), ['', 'N/A', '-'], true) ? '' : trim($v);

        return trim(implode(' ', array_filter([$part($p->firstname), $part($p->middlename), $part($p->lastname), $part($p->suffix)])));
    }
}
