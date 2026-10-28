<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only HR trackers migrated from the legacy accounting portal:
 * movement tracker, work setup (transfer) tracker and resigned employees.
 * Nothing here writes to the database.
 */
class HrTrackerController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    private const PER_PAGE = 50;

    public const RESIGNED_PER_PAGE = [50, 100, 500];

    // Fallback labels from the HR portal for points whose offense has no description
    private const OFFENSE_LABELS = [
        '12' => 'Absent with proper called-in',
        '13' => 'Absent with proper called-in, Invalid reason',
        '65' => 'Forgot to clock in (first shift) and failed to submit form and Over-break',
        '15' => 'Late within 15 minutes',
        '16' => 'Late 16 minutes and up with called-in',
        '17' => 'Late 16 minutes and up without called-in',
        '22' => 'Forgot to clock in (first shift) and failed to submit form',
        '19' => 'Over-Break (lunch)',
        '63' => 'Forgot to clock in/out (Lunch) w/ non-work related reason',
        '62' => 'Absent w/ CI w/in 30mins prior shift & 15 mins after',
        '66' => 'Forgot to clock in (first shift) and failed to submit form and Missed Out/In (Lunch)',
        '78' => 'Sick leave w/o supporting documents',
        '72' => 'Government Sanction',
        '67' => 'Internet Outage',
        '68' => 'Natural Calamity',
        '77' => 'PC Problem',
        '64' => 'Power Outage',
        '73' => 'Transportation Issue',
        '79' => 'SL with proper CI, invalid reason',
        '83' => 'Server Down/HRIS unreachable',
        '80' => 'SL w/ CI w/in 30 mins prior shift & 15 mins',
        '84' => 'Required Duty',
    ];

    /**
     * Company, department, job and shift changes from movement_tracker.
     */
    public function movement(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $companies = $this->companies();
        $codes = $companies->pluck('companycode')->all();
        $ctx = $this->context($request, $codes, true);

        $base = $conn->table('movement_tracker as mt')
            ->join('employee_details as ed', 'ed.idno', '=', 'mt.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'ed.idno')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $codes);
        $this->applySearch($base, $ctx['search']);
        $this->applyDates($base, 'mt.effectivitydate', $ctx);

        [$company, $tabs] = $this->companyTabs($companies, $ctx, (clone $base)
            ->selectRaw('ed.company, COUNT(*) AS n')
            ->groupBy('ed.company')
            ->pluck('n', 'company'));

        // "From" columns hold names; "to" columns hold a company code or department / job title id
        $rows = (clone $base)
            ->leftJoin('settings as c2', 'c2.companycode', '=', 'mt.companyto')
            ->leftJoin('department as d2', 'd2.id', '=', 'mt.departmentto')
            ->leftJoin('jobtitle as jt2', 'jt2.id', '=', 'mt.jobto')
            ->where('ed.company', $company)
            ->select(
                'ep.idno', 'ep.lastname', 'ep.firstname', 'ep.middlename', 'ep.suffix',
                'mt.companyfrom', 'mt.companyto', 'c2.companyname as companyto_name',
                'mt.departmentfrom', 'mt.departmentto', 'd2.department as departmentto_name',
                'mt.jobfrom', 'mt.jobto', 'jt2.jobtitle as jobto_title',
                'mt.shiftfrom', 'mt.shiftto', 'mt.effectivitydate'
            )
            ->orderByDesc('mt.effectivitydate')
            ->orderBy('ep.lastname')
            ->orderByDesc('mt.addeddatetime')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function ($r) {
                $r->company_from = $this->clean($r->companyfrom);
                $r->company_to = $this->clean($r->companyto_name ?? $r->companyto);
                $r->dept_from = $this->clean($r->departmentfrom);
                $r->dept_to = $this->clean($r->departmentto_name ?? $r->departmentto);
                $r->job_from = $this->clean($r->jobfrom);
                $r->job_to = $this->clean($r->jobto_title ?? $r->jobto);
                $r->shift_from = $this->shiftLabel($r->shiftfrom);
                $r->shift_to = $this->shiftLabel($r->shiftto);

                return $r;
            });

        $years = $conn->table('movement_tracker')
            ->where('effectivitydate', '>', '0001-01-01')
            ->selectRaw('DISTINCT YEAR(effectivitydate) AS y')
            ->orderByDesc('y')
            ->pluck('y');

        return view('hr.movement-tracker', $this->listData($ctx, $company, $tabs, $rows, $years));
    }

    /**
     * Approved location transfers (work_transfer) and WFH / hybrid setups
     * (wfh_application), each with a second row on its end date.
     */
    public function workSetup(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $companies = $this->companies();
        $codes = $companies->pluck('companycode')->all();
        $ctx = $this->context($request, $codes, true);

        $onsite = "CONCAT('OS (', COALESCE(ed.work_area, 'Unknown'), ')')";
        $setup = "CASE WHEN wf.hso_type = 1 THEN 'Hybrid' ELSE 'WFH' END";

        $union = $this->workSetupPart('work_transfer as wt', 'wt.date_transfer', 'wt', "wt.prev_loc", "CONCAT(wt.new_loc, ' - OS')", "'-'", "'-'", 1, $ctx, $codes)
            ->unionAll($this->workSetupPart('work_transfer as wt', 'wt.end_date', 'wt', "CONCAT(wt.new_loc, ' - OS')", 'wt.prev_loc', "'-'", "'-'", 1, $ctx, $codes))
            ->unionAll($this->workSetupPart('wfh_application as wf', 'wf.date_effective', 'wf', "'-'", "'-'", $onsite, $setup, 2, $ctx, $codes))
            ->unionAll($this->workSetupPart('wfh_application as wf', 'wf.end_date', 'wf', "'-'", "'-'", $setup, $onsite, 2, $ctx, $codes));

        $all = fn () => $conn->query()->fromSub($union, 'm');

        [$company, $tabs] = $this->companyTabs($companies, $ctx, $all()
            ->selectRaw('company, COUNT(*) AS n')
            ->groupBy('company')
            ->pluck('n', 'company'));

        $highlight = $this->upcomingDates();

        $rows = $all()
            ->where('company', $company)
            ->orderByDesc('effective_date')
            ->orderBy('priority')
            ->orderBy('lastname')
            ->orderByDesc('added_datetime')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function ($r) use ($highlight) {
                $r->is_upcoming = in_array($r->effective_date, $highlight, true);

                return $r;
            });

        $years = $conn->query()->fromSub(
            $conn->table('work_transfer')->selectRaw('YEAR(date_transfer) AS y')->whereNotNull('date_transfer')
                ->union($conn->table('work_transfer')->selectRaw('YEAR(end_date)')->whereNotNull('end_date'))
                ->union($conn->table('wfh_application')->selectRaw('YEAR(date_effective)')->whereNotNull('date_effective'))
                ->union($conn->table('wfh_application')->selectRaw('YEAR(end_date)')->whereNotNull('end_date')),
            'years'
        )->where('y', '>', 0)->orderByDesc('y')->pluck('y');

        return view('hr.work-setup-tracker', $this->listData($ctx, $company, $tabs, $rows, $years));
    }

    /**
     * Resigned employees with their latest resignation record.
     */
    public function resigned(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $companies = $this->companies();
        $codes = $companies->pluck('companycode')->all();
        $ctx = $this->context($request, $codes, false);
        $perPage = (int) ($request->validate([
            'per_page' => ['nullable', 'in:' . implode(',', self::RESIGNED_PER_PAGE)],
        ])['per_page'] ?? self::RESIGNED_PER_PAGE[0]);

        $latest = $conn->table('resignation')->selectRaw('idno, MAX(id) AS id')->groupBy('idno');

        $base = $conn->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->leftJoinSub($latest, 'rl', 'rl.idno', '=', 'ep.idno')
            ->leftJoin('resignation as r', 'r.id', '=', 'rl.id')
            ->where('ed.status', 'like', '%RESIGNED%')
            ->whereIn('ed.company', $codes);
        $this->applySearch($base, $ctx['search']);

        // Year options come from the company's resignations before the year filter is applied
        $yearsBase = clone $base;

        if ($ctx['year'] !== null) {
            $base->whereYear('r.dateresigned', $ctx['year']);
        }

        [$company, $tabs] = $this->companyTabs($companies, $ctx, (clone $base)
            ->selectRaw('ed.company, COUNT(*) AS n')
            ->groupBy('ed.company')
            ->pluck('n', 'company'));

        $rows = (clone $base)
            ->leftJoin('jobtitle as jt', 'jt.id', '=', 'ed.designation')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.company', $company)
            ->select(
                'ep.idno', 'ep.lastname', 'ep.firstname', 'ep.middlename', 'ep.suffix', 'ep.birthdate',
                'jt.jobtitle', 'd.department', 'ed.dateofhired', 'r.dateresigned', 'r.reason'
            )
            ->orderByDesc('r.dateresigned')
            ->orderBy('ep.lastname')
            ->orderBy('ep.idno')
            ->paginate($perPage)
            ->withQueryString();

        $years = $yearsBase
            ->where('ed.company', $company)
            ->whereNotNull('r.dateresigned')
            ->where('r.dateresigned', '>', '0001-01-01')
            ->selectRaw('DISTINCT YEAR(r.dateresigned) AS y')
            ->orderByDesc('y')
            ->pluck('y');

        return view('hr.resigned', $this->listData($ctx, $company, $tabs, $rows, $years) + [
            'perPage'     => $perPage,
            'listContext' => $this->resignedListContext($ctx, $company, $perPage, $rows->currentPage()),
        ]);
    }

    /**
     * View-only profile of one resigned employee: details, latest resignation,
     * infractions and attendance points (all years).
     */
    public function resignedShow(Request $request, string $idno): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $companies = $this->companies();
        $codes = $companies->pluck('companycode')->all();

        $det = $conn->table('employee_details as ed')
            ->leftJoin('jobtitle as jt', 'jt.id', '=', 'ed.designation')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.idno', $idno)
            ->where('ed.status', 'like', '%RESIGNED%')
            ->whereIn('ed.company', $codes)
            ->orderByDesc('ed.id')
            ->first([
                'ed.company', 'ed.status', 'ed.startshift', 'ed.endshift', 'ed.location', 'ed.work_area',
                'ed.dateofhired', 'ed.dateofregular', 'jt.jobtitle', 'd.department',
            ]);
        abort_if(!$det, 404);

        $p = $conn->table('employee_profile')
            ->where('idno', $idno)
            ->first([
                'idno', 'lastname', 'firstname', 'middlename', 'suffix', 'nickname', 'birthdate', 'sex', 'civilstatus',
                'eligibility', 'address', 'phone_number', 'comp_email', 'contactperson', 'contactnumber',
            ]);
        abort_if(!$p, 404);

        // Latest resignation, same record the list shows
        $resignation = $conn->table('resignation')
            ->where('idno', $idno)
            ->orderByDesc('id')
            ->first(['dateresigned', 'reason']);

        $infractions = $conn->table('infraction')
            ->where('idno', $idno)
            ->orderByDesc('dateissued')
            ->get([
                'dateissued', 'dateserved', 'dateofincident', 'typeofmemo', 'typeofoffense',
                'points', 'memonumber', 'typecat', 'dateofsuspension',
            ]);

        $points = $conn->table('points as p')
            ->leftJoin('offense as o', 'o.id', '=', 'p.offense')
            ->where('p.idno', $idno)
            ->orderByDesc('p.logindate')
            ->get(['p.logindate', 'p.points', 'p.offense', 'o.description as offense_description'])
            ->map(function ($r) {
                $r->label = $r->offense_description
                    ?: (self::OFFENSE_LABELS[(string) $r->offense] ?? 'Offense #' . $r->offense);

                return $r;
            });

        $yearOf = fn (?string $date) => $this->validDate($date) ? date('Y', strtotime($date)) : 'Undated';

        $hired = $this->validDate($det->dateofhired);
        $resigned = $this->validDate($resignation->dateresigned ?? null);

        $ctx = $this->context($request, $codes, false);
        $perPage = (int) ($request->validate([
            'per_page' => ['nullable', 'in:' . implode(',', self::RESIGNED_PER_PAGE)],
        ])['per_page'] ?? self::RESIGNED_PER_PAGE[0]);

        // Newest year first, records without a date last
        $byYear = fn ($rows, string $col) => $rows
            ->groupBy(fn ($r) => $yearOf($r->$col))
            ->sortKeysUsing(fn ($a, $b) => match (true) {
                $a === 'Undated' => 1,
                $b === 'Undated' => -1,
                default          => strcmp((string) $b, (string) $a),
            });

        return view('hr.resigned-profile', [
            'p'                => $p,
            'det'              => $det,
            'idno'             => $idno,
            'fullName'         => $this->displayName($p),
            'initials'         => strtoupper(mb_substr((string) $p->firstname, 0, 1) . mb_substr((string) $p->lastname, 0, 1)),
            'companyname'      => $companies->firstWhere('companycode', $det->company)->companyname ?? $det->company,
            'resignation'      => $resignation,
            'service'          => $hired && $resigned ? $this->serviceLabel($hired, $resigned) : null,
            'shift'            => $det->startshift && $det->endshift
                ? date('h:i A', strtotime($det->startshift)) . ' – ' . date('h:i A', strtotime($det->endshift)) : null,
            'infractionsByYear' => $byYear($infractions, 'dateissued'),
            'pointsByYear'      => $byYear($points, 'logindate'),
            'infractionCount'   => $infractions->count(),
            'listUrl'           => route('hr.resigned', $this->resignedListContext(
                $ctx, $ctx['company'], $perPage, (int) $request->query('page', 1)
            )),
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
     * Search, company, year, date range and page from the query string.
     * The trackers default to the current year; a date range replaces the year.
     */
    private function context(Request $request, array $companyCodes, bool $defaultCurrentYear): array
    {
        $v = $request->validate([
            'search'  => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'in:' . implode(',', $companyCodes)],
            'year'    => ['nullable', 'regex:/^(ALL|\d{4})$/'],
            'from'    => ['nullable', 'date_format:Y-m-d'],
            'to'      => ['nullable', 'date_format:Y-m-d'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ]);

        $from = $v['from'] ?? null;
        $to = $v['to'] ?? null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $yearParam = $v['year'] ?? ($defaultCurrentYear ? (string) now('Asia/Manila')->year : 'ALL');
        $year = $from || $to || $yearParam === 'ALL' ? null : (int) $yearParam;
        $search = trim((string) ($v['search'] ?? ''));

        return [
            'search'    => $search,
            'company'   => $v['company'] ?? null,
            'yearParam' => $yearParam,
            'year'      => $year,
            'from'      => $from,
            'to'        => $to,
            'searching' => $search !== '',
        ];
    }

    // ID or name, the same matching as the HR roster search
    private function applySearch(Builder $q, string $search): void
    {
        if ($search === '') {
            return;
        }

        $s = '%' . addcslashes($search, '%_\\') . '%';
        $q->where(fn ($w) => $w
            ->where('ep.idno', 'like', $s)
            ->orWhereRaw("CONCAT_WS(' ', ep.lastname, ep.firstname, ep.middlename) LIKE ?", [$s])
            ->orWhereRaw("CONCAT_WS(' ', ep.firstname, ep.middlename, ep.lastname) LIKE ?", [$s])
            ->orWhereRaw("CONCAT(ep.lastname, ', ', ep.firstname) LIKE ?", [$s]));
    }

    private function applyDates(Builder $q, string $col, array $ctx): void
    {
        $q->when($ctx['from'], fn ($w, $from) => $w->where($col, '>=', $from))
            ->when($ctx['to'], fn ($w, $to) => $w->where($col, '<=', $to))
            ->when($ctx['year'], fn ($w, $year) => $w->whereYear($col, $year));
    }

    /**
     * One leg of the work setup union: approved, active employees with a
     * movement on $dateCol. Column expressions are fixed SQL, never user input.
     */
    private function workSetupPart(
        string $table, string $dateCol, string $alias,
        string $locFrom, string $locTo, string $setupFrom, string $setupTo,
        int $priority, array $ctx, array $codes
    ): Builder {
        $q = DB::connection(self::HRIS_CONNECTION)->table($table)
            ->join('employee_details as ed', "$alias.idno", '=', 'ed.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'ed.idno')
            ->selectRaw("ed.company, ep.idno, ep.lastname, ep.firstname, ep.middlename, ep.suffix,
                $locFrom AS location_from, $locTo AS location_to,
                $setupFrom AS setup_from, $setupTo AS setup_to,
                $dateCol AS effective_date, $alias.datetime AS added_datetime, $priority AS priority")
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $codes)
            ->whereNotNull($dateCol)
            // Prefix match: '%Approved%' would also match "Disapproved - ..."
            ->where("$alias.application_status", 'like', 'Approved%');
        $this->applySearch($q, $ctx['search']);
        $this->applyDates($q, $dateCol, $ctx);

        return $q;
    }

    /**
     * Dates the legacy tracker highlights: today and the next day, with
     * Sunday and Monday moved forward to Tuesday.
     */
    private function upcomingDates(): array
    {
        $skip = fn (Carbon $d) => match ($d->dayOfWeekIso) {
            7       => $d->addDays(2),
            1       => $d->addDay(),
            default => $d,
        };

        $first = $skip(now('Asia/Manila')->startOfDay());
        $second = $skip($first->copy()->addDay());

        return [$first->toDateString(), $second->toDateString()];
    }

    /**
     * Selected company (while searching, the first one with matches) and its tabs.
     */
    private function companyTabs($companies, array $ctx, $counts): array
    {
        $company = $ctx['company'] ?? $companies->first()->companycode ?? null;
        if ($ctx['searching'] && (int) ($counts[$company] ?? 0) === 0) {
            $company = $companies->pluck('companycode')->first(fn ($c) => (int) ($counts[$c] ?? 0) > 0) ?? $company;
        }

        $tabs = $companies->map(fn ($c) => [
            'code'    => $c->companycode,
            'name'    => $c->companyname,
            'matches' => (int) ($counts[$c->companycode] ?? 0),
        ]);

        return [$company, $tabs];
    }

    private function listData(array $ctx, ?string $company, $tabs, $rows, $years): array
    {
        return [
            'rows'        => $rows,
            'companyTabs' => $tabs,
            'company'     => $company,
            'years'       => $years,
            'ctx'         => $ctx,
            'filtering'   => $ctx['searching'] || $ctx['from'] || $ctx['to'],
        ];
    }

    /**
     * Query string that brings the user back to the same resigned list view.
     */
    private function resignedListContext(array $ctx, ?string $company, int $perPage, int $page): array
    {
        return array_filter([
            'search'   => $ctx['search'],
            'company'  => $company,
            'year'     => $ctx['yearParam'] !== 'ALL' ? $ctx['yearParam'] : null,
            'per_page' => $perPage !== self::RESIGNED_PER_PAGE[0] ? $perPage : null,
            'page'     => $page > 1 ? $page : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    // Legacy format: "2 yr, 3 mo, 5 day"
    private function serviceLabel(string $from, string $to): string
    {
        $i = Carbon::parse($from)->diff(Carbon::parse($to));
        $parts = array_filter([
            $i->y ? "{$i->y} yr" : null,
            $i->m ? "{$i->m} mo" : null,
            $i->d ? "{$i->d} day" : null,
        ]);

        return $parts ? implode(', ', $parts) : '< 1 day';
    }

    private function validDate(?string $date): ?string
    {
        return $date && !in_array($date, ['0000-00-00', '0001-01-01'], true) ? $date : null;
    }

    private function displayName(object $p): string
    {
        $part = fn ($v) => in_array(strtoupper(trim((string) $v)), ['', 'N/A', '-'], true) ? '' : trim($v);

        return trim(implode(' ', array_filter([$part($p->firstname), $part($p->middlename), $part($p->lastname), $part($p->suffix)])));
    }

    private function clean(?string $v): string
    {
        $v = trim((string) $v);

        return $v === '' ? '-' : $v;
    }

    // "00:00:00-09:00:00" → "12:00 AM – 09:00 AM"; anything else is shown as stored
    private function shiftLabel(?string $shift): string
    {
        $shift = trim((string) $shift);
        if (preg_match('/^(\d{1,2}:\d{2})(?::\d{2})?\s*-\s*(\d{1,2}:\d{2})(?::\d{2})?$/', $shift, $m)) {
            return date('h:i A', strtotime($m[1])) . ' – ' . date('h:i A', strtotime($m[2]));
        }

        return $shift === '' ? '-' : $shift;
    }
}
