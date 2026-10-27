<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesHrisStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only list of hris.overtime_application, grouped company → department,
 * filtered by OT date. Same page structure as LeaveApplicationController;
 * overtime.js draws each department's table and handles the search.
 */
class OvertimeApplicationController extends Controller
{
    use ParsesHrisStatus;

    private const HRIS_CONNECTION = 'hris';

    public function index(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $thisYear = (int) now('Asia/Manila')->year;

        // Years that have overtime (ignoring obviously bad dates), always including this year.
        $years = $conn->table('overtime_application')
            ->whereBetween('otdate', ['2000-01-01', ($thisYear + 1) . '-12-31'])
            ->selectRaw('DISTINCT YEAR(otdate) AS y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->push($thisYear)
            ->unique()
            ->sortDesc()
            ->values();

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'in:' . $years->implode(',')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $year = (int) ($validated['year'] ?? $thisYear);

        // The date filter narrows within the selected year.
        $yearStart = "{$year}-01-01";
        $yearEnd   = "{$year}-12-31";
        $from = max($yearStart, $validated['from'] ?? $yearStart);
        $to   = min($yearEnd, $validated['to'] ?? $yearEnd);

        $companies = $conn->table('settings')
            ->select('companycode', 'companyname')
            ->orderBy('companycode')
            ->get();

        // Fetch from the day before $from: an overnight-shift OT moves forward a day
        // (see otDate()), and the range is then applied to the date that is shown.
        $rows = $from > $to ? collect() : $conn->table('overtime_application as ot')
            ->join('employee_details as ed', 'ed.idno', '=', 'ot.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'ot.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            ->whereBetween('ot.otdate', [date('Y-m-d', strtotime($from . ' -1 day')), $to])
            ->select(
                'ot.id',
                'ot.idno',
                'ot.ot_type',
                'ot.otdate',
                'ot.ottime',
                'ot.reasons',
                'ot.app_status',
                'ot.datearray',
                'ot.timearray',
                'ot.approver_remarks',
                'ep.lastname',
                'ep.firstname',
                'ed.startshift',
                'ed.company',
                'ed.department as department_id',
                'd.department as department_name'
            )
            ->get()
            ->each(fn ($r) => $r->shown_date = $this->otDate($r->otdate, $r->startshift))
            ->filter(fn ($r) => $r->shown_date >= $from && $r->shown_date <= $to)
            ->sortBy([['shown_date', 'desc'], ['datearray', 'desc'], ['timearray', 'desc'], ['id', 'desc']])
            ->values();

        $tabs = $companies->map(function ($company) use ($rows) {
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

        return view('leaves.overtime', [
            'years'     => $years,
            'year'      => $year,
            'from'      => $validated['from'] ?? null,
            'to'        => $validated['to'] ?? null,
            'rangeFrom' => $from,
            'rangeTo'   => $to,
            'tabs'      => $tabs,
            'total'     => $rows->count(),
        ]);
    }

    /**
     * One row as the page shows it (names match the column headers).
     */
    private function present(object $r): array
    {
        [$kind, $detail, $flagged] = $this->parseStatus($r->app_status);

        $applied = $r->datearray
            ? date('M d, Y', strtotime($r->datearray)) . ($r->timearray ? ' ' . date('h:i A', strtotime(substr($r->timearray, 0, 8))) : '')
            : null;

        $ottime = trim((string) $r->ottime);
        $ts = $ottime !== '' ? strtotime($ottime) : false;

        return [
            'id'       => (string) $r->idno,
            'name'     => trim(($r->lastname ?? '') . ', ' . ($r->firstname ?? ''), ', ') ?: (string) $r->idno,
            'type'     => $r->ot_type ?: '',
            'date'     => date('M d, Y', strtotime($r->shown_date)),
            'time'     => $ts !== false ? date('h:i A', $ts) : ($ottime ?: null),
            'reason'   => $r->reasons,
            'applied'  => $applied,
            'status'   => $kind,
            'statusBy' => $detail,
            'flagged'  => $flagged,
            'apNotes'  => $r->approver_remarks,
        ];
    }

    /**
     * Shown OT date, as the legacy HRIS overtime page shows it: when the
     * employee's shift starts at 11 PM or 12 AM, the OT falls on the next day.
     */
    private function otDate(string $otdate, ?string $startshift): string
    {
        $hour = $startshift ? (int) date('H', strtotime($startshift)) : null;

        return ($hour === 23 || $hour === 0)
            ? date('Y-m-d', strtotime($otdate . ' +1 day'))
            : $otdate;
    }
}
