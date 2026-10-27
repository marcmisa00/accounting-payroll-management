<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only list of hris.time_adjustments, grouped company → department,
 * filtered by date_affected. Same page structure as LeaveApplicationController;
 * time-adjustments.js draws each department's table and handles the search.
 */
class TimeAdjustmentController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    // Order and labels of the attendance fields shown in Original / Requested (as in the legacy page)
    private const FIELDS = [
        'shift'     => 'Shift',
        'time_in'   => 'Time in',
        'lunch_out' => 'Lunch out',
        'lunch_in'  => 'Lunch in',
        'time_out'  => 'Time out',
        'ot'        => 'OT',
    ];

    public function index(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $thisYear = (int) now('Asia/Manila')->year;

        // Years that have adjustments (ignoring obviously bad dates), always including this year.
        $years = $conn->table('time_adjustments')
            ->whereBetween('date_affected', ['2000-01-01', ($thisYear + 1) . '-12-31'])
            ->selectRaw('DISTINCT YEAR(date_affected) AS y')
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

        $rows = $from > $to ? collect() : $conn->table('time_adjustments as ta')
            ->join('employee_details as ed', 'ed.idno', '=', 'ta.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            ->whereBetween('ta.date_affected', [$from, $to])
            ->select(
                'ta.id',
                'ta.idno',
                'ta.employee_name',
                'ta.date_affected',
                'ta.adjustment_type',
                'ta.previous_data',
                'ta.adjusted_data',
                'ta.reason',
                'ta.submitted_at',
                'ta.status',
                'ta.approved_by',
                'ta.approved_at',
                'ta.final_status',
                'ta.final_approvedBy',
                'ta.final_approvedOn',
                'ta.hrNotes',
                'ta.monitoringNotes',
                'ta.approverNotes',
                'ed.company',
                'ed.department as department_id',
                'd.department as department_name'
            )
            ->orderByDesc('ta.date_affected')
            ->orderByDesc('ta.id')
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

        return view('leaves.time-adjustments', [
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
        $previous = json_decode($r->previous_data ?? 'null', true);
        $adjusted = json_decode($r->adjusted_data ?? 'null', true);
        $previous = is_array($previous) ? $previous : [];
        $adjusted = is_array($adjusted) ? $adjusted : [];

        $changed = $this->changedFields($previous, $adjusted);

        $status = in_array($r->status, ['Pending', 'Approved', 'Rejected'], true) ? $r->status : null;

        return [
            'id'        => (string) $r->idno,
            'name'      => trim((string) $r->employee_name) ?: (string) $r->idno,
            'type'      => $r->adjustment_type ?: '—',
            'date'      => $this->date($r->date_affected),
            // null → no attendance record existed (e.g. double shift)
            'original'  => $previous ? $this->fieldList($changed, $previous) : null,
            'requested' => $this->fieldList($changed, $adjusted),
            'reason'    => $r->reason,
            'submitted' => $this->dateTime($r->submitted_at),
            'status'    => $status ? strtolower($status) : 'other',
            'statusLabel' => $status ?? ($r->status ?: 'Unknown'),
            'statusBy'  => $this->byOn($r->approved_by, $r->approved_at),
            'final'     => $this->clean($r->final_status),
            'finalBy'   => $this->byOn($r->final_approvedBy, $r->final_approvedOn),
            'hrNotes'   => $this->note($r->hrNotes),
            'monNotes'  => $this->note($r->monitoringNotes),
            'apNotes'   => $this->note($r->approverNotes),
        ];
    }

    /**
     * Requested fields whose value differs from the original (legacy getChangedFields).
     */
    private function changedFields(array $previous, array $adjusted): array
    {
        $changed = [];

        foreach ($adjusted as $key => $new) {
            if ($this->isBlank($new)) {
                continue;
            }

            $old = $previous[$key] ?? null;

            if (is_string($old) && is_string($new)) {
                $old = trim($old);
                $new = trim($new);
            }

            if ($old !== $new) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * [['label' => 'Time in', 'value' => '05:00 AM'], ...] for the changed fields, in display order.
     */
    private function fieldList(array $changed, array $data): array
    {
        $list = [];

        foreach (self::FIELDS as $key => $label) {
            if (! in_array($key, $changed, true) || $this->isBlank($data[$key] ?? null)) {
                continue;
            }

            $list[] = ['label' => $label, 'value' => $this->formatValue($key, (string) $data[$key])];
        }

        return $list;
    }

    private function isBlank($value): bool
    {
        return $value === null || $value === '' || $value === 'NONE' || $value === 'NULL';
    }

    // "0" is how HRIS stores a missing log; times and shifts are shown in 12-hour format.
    private function formatValue(string $key, string $value): string
    {
        if ($value === '0' || $value === 'null') {
            return 'No log';
        }

        if ($key === 'shift' && str_contains($value, '-')) {
            $parts = array_map('trim', explode('-', $value));
            if (count($parts) === 2) {
                return $this->time($parts[0]) . ' – ' . $this->time($parts[1]);
            }
        }

        return $this->time($value);
    }

    private function time(string $value): string
    {
        $ts = strtotime($value);

        return $ts !== false ? date('h:i A', $ts) : $value;
    }

    private function date($value): ?string
    {
        return ($value && $value !== '0000-00-00') ? date('M d, Y', strtotime($value)) : null;
    }

    private function dateTime($value): ?string
    {
        return ($value && ! str_starts_with($value, '0000-00-00')) ? date('M d, Y h:i A', strtotime($value)) : null;
    }

    // Some stored names carry stray whitespace/line breaks, e.g. "Acla (Human Resource Generalist\r)".
    private function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
        $value = preg_replace('/\s+\)/', ')', $value);

        return $value !== '' ? $value : null;
    }

    // "Meto (Team Leader) · Oct 27, 2026 07:43 AM"
    private function byOn(?string $by, $on): ?string
    {
        $by = $this->clean($by);
        $on = $this->dateTime($on);

        return trim($by . ($by && $on ? ' · ' : '') . $on) ?: null;
    }

    /**
     * Notes are stored as JSON {note, noted_by, noted_on}; plain text is shown as-is.
     */
    private function note(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '' || $raw === 'null') {
            return null;
        }

        $data = json_decode($raw, true);

        if (is_array($data) && isset($data['note'])) {
            $text = trim((string) $data['note']);

            return $text === '' ? null : [
                'text' => $text,
                'by'   => $this->byOn($data['noted_by'] ?? null, $data['noted_on'] ?? null),
            ];
        }

        return ['text' => $raw, 'by' => null];
    }
}
