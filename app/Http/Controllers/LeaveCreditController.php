<?php

namespace App\Http\Controllers;

use App\Services\LeaveCreditService;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only leave credits of active employees, grouped company → department
 * (migrated from the legacy accounting/leavecredits.php). The page receives
 * every row once; lv-tabs.js draws each department's table and searches across them.
 */
class LeaveCreditController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    // Shift start times the legacy page treats as graveyard
    private const GRAVEYARD_STARTS = ['23:00', '00:00', '01:00'];

    // View dropdown: which leaves are shown as Credits / Used / Remaining
    public const BATCHES = [
        'regular' => ['label' => 'VL / SL / PTO', 'icon' => 'fa-calendar-days', 'leaves' => ['vl' => 'Vacation Leave', 'sl' => 'Sick Leave', 'pto' => 'Personal Time-Off (PTO)']],
        'special' => ['label' => 'SPL / BLP', 'icon' => 'fa-gift', 'leaves' => ['spl' => 'Solo Parent Leave (SPL)', 'blp' => 'Birthday Leave (BLP)']],
        'eo_q1'   => ['label' => 'EO Jan - Mar', 'icon' => 'fa-sun', 'leaves' => ['jan' => 'January EO', 'feb' => 'February EO', 'mar' => 'March EO']],
        'eo_q2'   => ['label' => 'EO Apr - Jun', 'icon' => 'fa-leaf', 'leaves' => ['apr' => 'April EO', 'may' => 'May EO', 'jun' => 'June EO']],
        'eo_q3'   => ['label' => 'EO Jul - Sep', 'icon' => 'fa-cloud-sun', 'leaves' => ['jul' => 'July EO', 'aug' => 'August EO', 'sep' => 'September EO']],
        'eo_q4'   => ['label' => 'EO Oct - Dec', 'icon' => 'fa-snowflake', 'leaves' => ['oct' => 'October EO', 'nov' => 'November EO', 'dec' => 'December EO']],
    ];

    // Leave key → type code used by the "Used" dates popup
    private const USED_TYPES = ['vl' => 'VL', 'sl' => 'SL', 'pto' => 'PTO', 'spl' => 'SPL', 'blp' => 'BLP'];

    public function __construct(private LeaveCreditService $credits)
    {
    }

    public function index(Request $request): View
    {
        $v = $request->validate([
            'batch'      => ['nullable', 'in:' . implode(',', array_keys(self::BATCHES))],
            'from_month' => ['nullable', 'integer', 'between:1,12'],
            'from_day'   => ['nullable', 'integer', 'between:1,31'],
            'to_month'   => ['nullable', 'integer', 'between:1,12'],
            'to_day'     => ['nullable', 'integer', 'between:1,31'],
        ]);

        $batch = $v['batch'] ?? 'regular';
        $leaves = self::BATCHES[$batch]['leaves'];

        // Eligibility filter by month-day (MMDD), only when both month and day are picked
        $md = fn ($month, $day) => $month && $day ? (int) sprintf('%02d%02d', $month, $day) : null;
        $fromMD = $md($v['from_month'] ?? null, $v['from_day'] ?? null);
        $toMD = $md($v['to_month'] ?? null, $v['to_day'] ?? null);

        $today = new DateTime(now('Asia/Manila')->format('Y-m-d'));
        $conn = DB::connection(self::HRIS_CONNECTION);

        $companies = $conn->table('settings')
            ->select('companycode', 'companyname')
            ->orderBy('companycode')
            ->get();

        $creditCols = collect(['vacationleave', 'vlused', 'sickleave', 'slused', 'pto', 'ptoused', 'spl', 'spl_used', 'bdayleave', 'blp_used'])
            ->merge(collect(LeaveCreditService::MONTHS)->flatMap(fn ($m) => ["{$m}_earlyout", "{$m}_eo_used"]))
            ->map(fn ($c) => "lc.$c")
            ->all();

        $employees = $conn->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->leftJoin('leave_credits as lc', 'lc.idno', '=', 'ep.idno')
            ->leftJoin('employee_payroll as pay', 'pay.idno', '=', 'ep.idno')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            ->select(array_merge([
                'ep.idno', 'ep.firstname', 'ep.lastname', 'ep.suffix',
                'ed.company', 'ed.department as department_id', 'd.department as department_name',
                'ed.status', 'ed.dateofhired', 'ed.dateofregular', 'ed.startshift',
                'pay.salary_type',
            ], $creditCols))
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->get();

        $rows = $employees->map(function ($e) use ($today, $leaves, $fromMD, $toMD) {
            $service = $this->credits->serviceData($e->dateofhired, $e->dateofregular, $today);

            if ($fromMD !== null || $toMD !== null) {
                if (!$service['eligibility']) {
                    return null;
                }
                $eligMD = (int) $service['eligibility']->format('md');
                if (($fromMD !== null && $eligMD < $fromMD) || ($toMD !== null && $eligMD > $toMD)) {
                    return null;
                }
            }

            $e->service = $service;
            $e->balances = array_intersect_key($this->credits->balances($e, $service, $today), $leaves);

            return $e;
        })->filter();

        $tabs = $this->groupTabs($companies, $rows);

        return view('hr.leave-credits', [
            'tabs'      => $tabs,
            'total'     => $rows->count(),
            'batch'     => $batch,
            'leaves'    => $leaves,
            'fromMonth' => $v['from_month'] ?? null,
            'fromDay'   => $v['from_day'] ?? null,
            'toMonth'   => $v['to_month'] ?? null,
            'toDay'     => $v['to_day'] ?? null,
            'filtered'  => $fromMD !== null || $toMD !== null,
        ]);
    }

    /**
     * Monthly leave credits (migrated from the legacy accounting/monthly_leave_credits.php):
     * previous-period totals stored in leave_credits_previous, for employees whose current
     * leave period starts in the chosen month, or whose eligibility falls in a month-day range.
     */
    public function monthly(Request $request): View
    {
        $v = $request->validate([
            'month'      => ['nullable', 'integer', 'between:1,12'],
            'from_month' => ['nullable', 'integer', 'between:1,12'],
            'from_day'   => ['nullable', 'integer', 'between:1,31'],
            'to_month'   => ['nullable', 'integer', 'between:1,12'],
            'to_day'     => ['nullable', 'integer', 'between:1,31'],
        ]);

        $now = now('Asia/Manila');
        $month = (int) ($v['month'] ?? $now->month);
        $leaves = self::BATCHES['regular']['leaves'];

        $md = fn ($m, $day) => $m && $day ? (int) sprintf('%02d%02d', $m, $day) : null;
        $fromMD = $md($v['from_month'] ?? null, $v['from_day'] ?? null);
        $toMD = $md($v['to_month'] ?? null, $v['to_day'] ?? null);
        $filtered = $fromMD !== null || $toMD !== null;

        $today = new DateTime($now->format('Y-m-d'));
        $conn = DB::connection(self::HRIS_CONNECTION);

        $companies = $conn->table('settings')
            ->select('companycode', 'companyname')
            ->orderBy('companycode')
            ->get();

        $employees = $conn->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->join('leave_credits_previous as lcp', 'lcp.idno', '=', 'ep.idno')
            ->join('leave_credits as lc', 'lc.idno', '=', 'ep.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->leftJoin('employee_payroll as pay', 'pay.idno', '=', 'ep.idno')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            // The month view is ignored while the eligibility filter is on, like the legacy page
            ->when(!$filtered, fn ($q) => $q->whereMonth('lc.periodfrom', $month))
            ->select([
                'ep.idno', 'ep.firstname', 'ep.lastname', 'ep.suffix',
                'ed.company', 'ed.department as department_id', 'd.department as department_name',
                'ed.dateofhired', 'ed.dateofregular', 'ed.startshift',
                'pay.salary_type',
                'lcp.vacationleave', 'lcp.vlused', 'lcp.sickleave', 'lcp.slused', 'lcp.pto', 'lcp.ptoused',
            ])
            ->orderByRaw('MONTH(lc.periodfrom), DAY(lc.periodfrom)')
            ->orderBy('ep.lastname')
            ->get();

        $columns = ['vl' => ['vacationleave', 'vlused'], 'sl' => ['sickleave', 'slused'], 'pto' => ['pto', 'ptoused']];

        $rows = $employees->map(function ($e) use ($today, $columns, $fromMD, $toMD, $filtered) {
            $service = $this->credits->serviceData($e->dateofhired, $e->dateofregular, $today);

            if ($filtered) {
                if (!$service['eligibility']) {
                    return null;
                }
                $eligMD = (int) $service['eligibility']->format('md');
                $inRange = $fromMD !== null && $toMD !== null && $fromMD > $toMD
                    ? $eligMD >= $fromMD || $eligMD <= $toMD // range crossing the new year, e.g. Dec 28 - Jan 12
                    : ($fromMD === null || $eligMD >= $fromMD) && ($toMD === null || $eligMD <= $toMD);
                if (!$inRange) {
                    return null;
                }
            }

            // Stored values only, no formula fallback; an empty credit shows blank and counts as 0
            $e->service = $service;
            $e->balances = collect($columns)->map(fn ($c) => [
                'credits'   => $e->{$c[0]} === null ? null : (float) $e->{$c[0]},
                'used'      => $e->{$c[1]} === null ? null : (float) $e->{$c[1]},
                'remaining' => (float) $e->{$c[0]} - (float) $e->{$c[1]},
            ])->all();

            return $e;
        })->filter();

        return view('hr.monthly-leave-credits', [
            'tabs'      => $this->groupTabs($companies, $rows),
            'total'     => $rows->count(),
            'leaves'    => $leaves,
            'month'     => $month,
            'fromMonth' => $v['from_month'] ?? null,
            'fromDay'   => $v['from_day'] ?? null,
            'toMonth'   => $v['to_month'] ?? null,
            'toDay'     => $v['to_day'] ?? null,
            'filtered'  => $filtered,
        ]);
    }

    /**
     * Dates behind a "Used" figure, for the popup (JSON).
     */
    public function usedDates(Request $request, string $idno): JsonResponse
    {
        $v = $request->validate([
            'type' => ['required', 'string', 'max:10'],
        ]);

        $exists = DB::connection(self::HRIS_CONNECTION)->table('employee_details')
            ->where('idno', $idno)
            ->where('status', 'not like', '%RESIGNED%')
            ->exists();
        abort_if(!$exists, 404);

        return response()->json(
            $this->credits->usedDates($idno, $v['type'], new DateTime(now('Asia/Manila')->format('Y-m-d')))
        );
    }

    /**
     * Rows grouped company → department tabs, keeping the rows' order inside each department.
     */
    private function groupTabs($companies, $rows)
    {
        return $companies->map(function ($company) use ($rows) {
            $companyRows = $rows->where('company', $company->companycode);

            $departments = $companyRows
                ->groupBy(fn ($r) => $r->department_id ?: 0)
                ->map(fn ($deptRows, $deptId) => [
                    'key'   => $company->companycode . '-' . $deptId,
                    'name'  => $deptRows->first()->department_name ?: 'No department',
                    'count' => $deptRows->count(),
                    'rows'  => $deptRows->map(fn ($r) => $this->present($r))->values(),
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            return [
                'code'        => $company->companycode,
                'name'        => $company->companyname,
                'count'       => $companyRows->count(),
                'departments' => $departments,
            ];
        });
    }

    /**
     * One row as the page shows it.
     */
    private function present(object $r): array
    {
        $s = $r->service;
        $suffix = trim((string) $r->suffix);
        $isGraveyard = $r->startshift && in_array(substr($r->startshift, 0, 5), self::GRAVEYARD_STARTS, true);

        return [
            'id'          => (string) $r->idno,
            'last'        => (string) $r->lastname,
            'name'        => trim($r->firstname . ($suffix !== '' && strtoupper($suffix) !== 'N/A' ? ' ' . $suffix : '')),
            'eligibility' => $s['eligibility']?->format('M d, Y'),
            'eligSort'    => $s['eligibility']?->format('Ymd'),
            'service'     => "{$s['years']} years {$s['months']} months {$s['days']} days",
            'serviceSort' => $s['years'] * 365 + $s['months'] * 30 + $s['days'],
            'period'      => $s['periodfrom'] ? $s['periodfrom']->format('M d, Y') . ' - ' . $s['periodto']->format('M d, Y') : null,
            'periodSort'  => $s['periodfrom']?->format('Ymd'),
            'tone'        => match (true) {
                in_array($r->salary_type, ['Fixed', 'Daily'], true) => 'blue',
                $isGraveyard                                         => 'gray',
                default                                              => null,
            },
            'leaves'      => collect($r->balances)->map(fn ($b, $key) => $b + [
                'type' => self::USED_TYPES[$key] ?? strtoupper($key) . '_EO',
            ])->all(),
        ];
    }
}
