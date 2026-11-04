<?php

namespace App\Http\Controllers;

use App\Services\AttendanceSummaryReport;
use App\Services\MissedLogsSummaryReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only attendance monitoring: every active employee's attendance for one
 * company over a date range, straight from hris.attendance.
 */
class AttendanceController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    /** Longest range one search may cover, in days (inclusive). */
    public const MAX_RANGE_DAYS = 31;

    /** Status filters, matched against the '/'-separated tokens in attendance.status. */
    public const STATUSES = [
        'present'   => ['label' => 'Present',   'token' => 'work'],
        'absent'    => ['label' => 'Absent',    'token' => 'ab'],
        'leave'     => ['label' => 'Leave',     'token' => 'leave'],
        'suspended' => ['label' => 'Suspended', 'token' => 'sus'],
    ];

    public function index(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $companies = $conn->table('settings')
            ->select('companycode', 'companyname')
            ->orderBy('companycode')
            ->get();

        [$defaultFrom, $defaultTo] = $this->currentPeriodRange($conn);

        $validated = $request->validate([
            'company'    => ['nullable', 'string', 'in:' . $companies->pluck('companycode')->implode(',')],
            'from'       => ['nullable', 'date_format:Y-m-d'],
            'to'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'department' => ['nullable', 'integer'],
            'employee'   => ['nullable', 'string', 'max:100'],
            'status'     => ['nullable', 'in:' . implode(',', array_keys(self::STATUSES))],
        ]);

        $company    = $validated['company'] ?? $companies->first()?->companycode;
        $from       = CarbonImmutable::parse($validated['from'] ?? $defaultFrom, 'Asia/Manila');
        $to         = CarbonImmutable::parse($validated['to'] ?? $defaultTo, 'Asia/Manila');
        $department = $validated['department'] ?? null;
        $employee   = trim((string) ($validated['employee'] ?? ''));
        $status     = $validated['status'] ?? '';

        $rangeError = null;
        if ($to->lessThan($from)) {
            $rangeError = 'The end date must be on or after the start date.';
        } elseif ((int) round($from->diffInDays($to, true)) + 1 > self::MAX_RANGE_DAYS) {
            $rangeError = 'Please pick a range of ' . self::MAX_RANGE_DAYS . ' days or less.';
        }

        $departments = $conn->table('employee_details as ed')
            ->join('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.company', $company)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->select('d.id', 'd.department')
            ->distinct()
            ->orderBy('d.department')
            ->get();

        $filters = [
            'company'    => $company,
            'from'       => $from->toDateString(),
            'to'         => $to->toDateString(),
            'department' => $department,
            'employee'   => $employee,
            'status'     => $status,
        ];

        if ($rangeError) {
            return view('attendance.index', $filters + [
                'companies'   => $companies,
                'departments' => $departments,
                'rangeError'  => $rangeError,
                'summary'     => null,
                'records'     => null,
                'corrections' => collect(),
            ]);
        }

        // Summary covers the whole range for the company/department/employee
        // filters (not the status filter, so the cards always show the full mix).
        $summary = $this->baseQuery($conn, $company, $from, $to, $department, $employee)
            ->selectRaw('COUNT(DISTINCT a.idno) AS employees')
            ->selectRaw('COUNT(*) AS records')
            ->selectRaw("SUM(a.status REGEXP '(^|/)work(/|$)') AS present")
            ->selectRaw("SUM(a.status REGEXP '(^|/)ab(/|$)') AS absent")
            ->selectRaw("SUM(a.status REGEXP '(^|/)leave(/|$)') AS on_leave")
            ->selectRaw("SUM(a.status REGEXP '(^|/)sus(/|$)') AS suspended")
            ->first();

        $records = $this->baseQuery($conn, $company, $from, $to, $department, $employee)
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->when($status !== '', function ($q) use ($status) {
                $q->whereRaw('a.status REGEXP ?', ['(^|/)' . self::STATUSES[$status]['token'] . '(/|$)']);
            })
            ->select(
                'a.id',
                'a.idno',
                'a.logindate',
                'a.attendance_dls',
                'a.loginam',
                'a.logoutam',
                'a.loginpm',
                'a.logoutpm',
                'a.status',
                'a.remarks',
                'a.otremarks',
                'a.ottime',
                'a.idle',
                'ep.lastname',
                'ep.firstname',
                'd.department as department_name'
            )
            ->selectRaw(self::WORK_DATE_SQL . ' AS work_date')
            ->orderBy('work_date')
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->orderBy('a.id')
            ->paginate(50)
            ->withQueryString();

        return view('attendance.index', $filters + [
            'companies'   => $companies,
            'departments' => $departments,
            'rangeError'  => null,
            'summary'     => $summary,
            'records'     => $records,
            'corrections' => $this->approvedCorrections($conn, collect($records->items())),
        ]);
    }

    /**
     * Attendance Summary (absences / late), as on the HR portal: one column per
     * day plus incident and leave totals per employee. Read only.
     */
    public function summary(Request $request, AttendanceSummaryReport $report): View
    {
        $scope = $this->summaryScope($request);

        $employees = AttendanceSummaryReport::filter(
            $report->employees($scope['company'], $scope['departments'], $scope['from'], $scope['to']),
            $scope['search'], $scope['fixed'], $scope['gy'], $scope['am'],
        );
        $dates = AttendanceSummaryReport::dates($scope['from'], $scope['to']);
        $employees->each(fn ($emp) => $emp->cells = $report->cells($emp, $dates));

        return view('attendance.summary', $scope + [
            'employees'  => $employees,
            'dates'      => $dates,
            'monthRange' => $this->monthRange($dates),
        ]);
    }

    /**
     * Missed Logs / OB Summary, as on the HR portal: the same day grid for lunch
     * over-breaks and missed logins, with totals and points. Read only.
     */
    public function missedSummary(Request $request, MissedLogsSummaryReport $report): View
    {
        $scope = $this->summaryScope($request);

        $employees = AttendanceSummaryReport::filter(
            $report->employees($scope['company'], $scope['departments'], $scope['from'], $scope['to']),
            $scope['search'], $scope['fixed'], $scope['gy'], $scope['am'],
        );
        if ($scope['incidents']) {
            $employees = $employees->filter(fn ($e) => $e->totals['total'] > 0)->values();
        }
        $dates = AttendanceSummaryReport::dates($scope['from'], $scope['to']);
        $employees->each(fn ($emp) => $emp->cells = $report->cells($emp, $dates));
        $codes = array_values(MissedLogsSummaryReport::CODES);

        return view('attendance.missed-summary', $scope + [
            'employees'  => $employees,
            'dates'      => $dates,
            'codes'      => $codes,
            'monthRange' => $this->monthRange($dates),
            // Totals of the rows shown, for the stat strip.
            'stats'      => [
                'employees'     => $employees->count(),
                'withIncidents' => $employees->filter(fn ($e) => $e->totals['total'] > 0)->count(),
                'codes'         => collect($codes)->mapWithKeys(fn ($c) => [$c => $employees->sum(fn ($e) => $e->totals[$c])])->all(),
                'points'        => $employees->sum(fn ($e) => $e->totals['points']),
            ],
        ]);
    }

    /**
     * Filters shared by the two summaries (HR's SummaryFilters): company,
     * optional department, a date range capped at MAX_RANGE_DAYS, search and
     * the shift checkboxes. An end before the start becomes the start.
     */
    private function summaryScope(Request $request): array
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $companies = $conn->table('settings')->select('companycode', 'companyname')->orderBy('companycode')->get();

        $validated = $request->validate([
            'company'    => ['nullable', 'string', 'in:' . $companies->pluck('companycode')->implode(',')],
            'from'       => ['nullable', 'date_format:Y-m-d'],
            'to'         => ['nullable', 'date_format:Y-m-d'],
            'department' => ['nullable', 'integer'],
            'search'     => ['nullable', 'string', 'max:100'],
            'am'         => ['nullable', 'in:1'],
            'gy'         => ['nullable', 'in:1'],
            'fixed'      => ['nullable', 'in:1'],
            'incidents'  => ['nullable', 'in:1'],
        ]);

        [$defaultFrom, $defaultTo] = $this->currentPeriodRange($conn);

        $company = (string) ($validated['company'] ?? $companies->first()?->companycode);
        $from = $validated['from'] ?? $defaultFrom;
        $to = max($validated['to'] ?? $defaultTo, $from);
        $maxTo = CarbonImmutable::parse($from, 'Asia/Manila')->addDays(self::MAX_RANGE_DAYS - 1)->toDateString();
        $department = isset($validated['department']) ? (int) $validated['department'] : null;

        return [
            'company'     => $company,
            'companyName' => optional($companies->firstWhere('companycode', $company))->companyname ?: $company,
            'department'  => $department,
            'departments' => $department ? [$department] : [],
            'deptName'    => $department ? $conn->table('department')->where('id', $department)->value('department') : null,
            'from'        => $from,
            'to'          => min($to, $maxTo),
            'rangeCapped' => $to > $maxTo,
            'search'      => trim((string) ($validated['search'] ?? '')),
            'am'          => isset($validated['am']),
            'gy'          => isset($validated['gy']),
            'fixed'       => isset($validated['fixed']),
            'incidents'   => isset($validated['incidents']),
        ];
    }

    /** "October 2026 → November 2026" when the range crosses a month, else null. */
    private function monthRange(array $dates): ?string
    {
        $months = collect($dates)->pluck('month')->unique()->values();

        return $months->count() > 1 ? $months->join(' → ') : null;
    }

    /**
     * The day a row counts for: attendance_dls = 1 rows belong to the day after
     * logindate (same rule as PayrollCalculationService).
     */
    private const WORK_DATE_SQL = "(CASE WHEN a.attendance_dls = 1 THEN DATE_ADD(DATE(a.logindate), INTERVAL 1 DAY) ELSE DATE(a.logindate) END)";

    /**
     * Attendance rows for active (non-resigned) employees of one company whose
     * work date (see WORK_DATE_SQL) falls in the range.
     */
    private function baseQuery($conn, string $company, CarbonImmutable $from, CarbonImmutable $to, $department, string $employee)
    {
        return $conn->table('attendance as a')
            ->join('employee_details as ed', 'ed.idno', '=', 'a.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'a.idno')
            ->where('ed.company', $company)
            ->where('ed.status', 'not like', '%RESIGNED%')
            // Index-friendly window (one extra day back catches dls rows), then the exact work-date match.
            ->whereBetween('a.logindate', [$from->subDay()->toDateString(), $to->toDateString() . ' 23:59:59'])
            ->whereRaw(self::WORK_DATE_SQL . ' BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()])
            ->when($department, fn ($q) => $q->where('ed.department', $department))
            ->when($employee !== '', function ($q) use ($employee) {
                $like = '%' . addcslashes($employee, '%_\\') . '%';
                $q->where(function ($q) use ($like) {
                    $q->where('a.idno', 'like', $like)
                        ->orWhere('ep.firstname', 'like', $like)
                        ->orWhere('ep.lastname', 'like', $like)
                        ->orWhereRaw("CONCAT(ep.firstname, ' ', ep.lastname) LIKE ?", [$like]);
                });
            });
    }

    /**
     * "idno|logindate" keys for rows on this page that have an approved missed-log
     * correction. Matched on the raw logindate, as the payroll engine does.
     */
    private function approvedCorrections($conn, $rows)
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $dates = $rows->pluck('logindate')->map(fn ($d) => substr((string) $d, 0, 10));

        return $conn->table('missed_log_application')
            ->whereIn('idno', $rows->pluck('idno')->unique()->values())
            ->whereBetween('datemissed', [$dates->min(), $dates->max()])
            ->where(function ($q) {
                $q->where('applic_status', 'like', 'Approved%')->orWhere('applic_status', 'like', '*Approved%');
            })
            ->get(['idno', 'datemissed'])
            ->map(fn ($m) => $m->idno . '|' . substr((string) $m->datemissed, 0, 10))
            ->unique()
            ->flip();
    }

    /**
     * Default range: the payroll period containing today, else the latest one
     * that has ended, else the last 15 days (same rule as the dashboard).
     */
    private function currentPeriodRange($conn): array
    {
        $today = now('Asia/Manila')->toDateString();

        $periods = $conn->table('payroll')
            ->select('periodfrom', 'periodto')
            ->orderByDesc('periodfrom')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [substr((string) $p->periodfrom, 0, 10), substr((string) $p->periodto, 0, 10)]);

        $period = $periods->first(fn ($p) => $p[0] <= $today && $p[1] >= $today)
            ?? $periods->first(fn ($p) => $p[1] <= $today);

        return $period ?? [now('Asia/Manila')->subDays(14)->toDateString(), $today];
    }
}
