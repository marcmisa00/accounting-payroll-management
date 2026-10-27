<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesHrisStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only list of hris.leave_application, grouped company → department.
 * The page receives the whole selected year once; leaves.js draws each
 * department's table on demand and searches across all of them.
 */
class LeaveApplicationController extends Controller
{
    use ParsesHrisStatus;

    private const HRIS_CONNECTION = 'hris';

    public function index(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $thisYear = (int) now('Asia/Manila')->year;

        // Years that have leaves (ignoring obviously bad dates), always including this year.
        $years = $conn->table('leave_application')
            ->whereBetween('dayfrom', ['2000-01-01', ($thisYear + 1) . '-12-31'])
            ->selectRaw('DISTINCT YEAR(dayfrom) AS y')
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

        // Leaves that overlap [from, to] (a leave spanning the boundary still shows).
        $rows = $from > $to ? collect() : $conn->table('leave_application as la')
            ->join('employee_details as ed', 'ed.idno', '=', 'la.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'la.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            ->where('la.dayfrom', '<=', $to)
            ->where('la.dayto', '>=', $from)
            ->select(
                'la.id',
                'la.idno',
                'la.leavetype',
                'la.numberofdays',
                'la.dayfrom',
                'la.dayto',
                'la.reason',
                'la.datearray',
                'la.timearray',
                'la.appstatus',
                'la.remarks',
                'la.approver_remarks',
                'ep.lastname',
                'ep.firstname',
                'ed.company',
                'ed.department as department_id',
                'd.department as department_name'
            )
            ->orderByDesc('la.dayfrom')
            ->orderByDesc('la.id')
            ->get();

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

        return view('leaves.applications', [
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
        [$kind, $detail, $flagged] = $this->parseStatus($r->appstatus);

        $applied = $r->datearray
            ? date('M d, Y', strtotime($r->datearray)) . ($r->timearray ? ' ' . date('h:i A', strtotime(substr($r->timearray, 0, 8))) : '')
            : null;

        return [
            'id'       => (string) $r->idno,
            'name'     => trim(($r->lastname ?? '') . ', ' . ($r->firstname ?? ''), ', ') ?: (string) $r->idno,
            'type'     => $r->leavetype ?: '—',
            'days'     => (int) $r->numberofdays,
            'start'    => $this->date($r->dayfrom),
            'end'      => $this->date($r->dayto),
            'reason'   => $r->reason,
            'applied'  => $applied,
            'status'   => $kind,
            'statusBy' => $detail,
            'flagged'  => $flagged,
            'hrNotes'  => $r->remarks,
            'apNotes'  => $r->approver_remarks,
        ];
    }

    private function date($value): ?string
    {
        return ($value && $value !== '0000-00-00') ? date('M d, Y', strtotime($value)) : null;
    }
}
