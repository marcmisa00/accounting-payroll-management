<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared by the HSO (wfh_application) and site transfer (work_transfer) pages:
 * year / date filters, company → department tabs, the IT / HR / Accounting
 * acknowledgement trail, and Accounting's acknowledge action.
 *
 * HRIS stores the trail in `acknowledged` as entries joined by ", <br>",
 * e.g. "HR [Oct 21, 2026 - 1:51 PM], <br>Accounting [Oct 22, 2026 - 9:05 AM]",
 * with the person in it_ / hr_ / acc_acknowledged_by. The format must stay
 * exactly as the HRIS portal writes it, because HR and IT read the same field.
 */
trait HandlesHrisApplications
{
    use ParsesHrisStatus;

    private function hris()
    {
        return DB::connection('hris');
    }

    private function companies(): Collection
    {
        return $this->hris()->table('settings')
            ->select('companycode', 'companyname')
            ->orderBy('companycode')
            ->get();
    }

    /**
     * Year list and the [from, to] range inside the selected year, as on the leave pages.
     */
    private function dateRange(Request $request, string $table, string $dateCol): array
    {
        $thisYear = (int) now('Asia/Manila')->year;

        $years = $this->hris()->table($table)
            ->whereBetween($dateCol, ['2000-01-01', ($thisYear + 1) . '-12-31'])
            ->selectRaw("DISTINCT YEAR($dateCol) AS y")
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->push($thisYear)
            ->unique()
            ->sortDesc()
            ->values();

        $v = $request->validate([
            'year' => ['nullable', 'integer', 'in:' . $years->implode(',')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d'],
        ]);

        $year = (int) ($v['year'] ?? $thisYear);
        $from = max("{$year}-01-01", $v['from'] ?? "{$year}-01-01");
        $to = min("{$year}-12-31", $v['to'] ?? "{$year}-12-31");

        return [
            'years'     => $years,
            'year'      => $year,
            'from'      => $v['from'] ?? null,
            'to'        => $v['to'] ?? null,
            'rangeFrom' => $from,
            'rangeTo'   => $to,
        ];
    }

    /**
     * Rows grouped company → department, with totals and how many still need Accounting.
     */
    private function tabs(Collection $companies, Collection $rows, callable $present): Collection
    {
        return $companies->map(function ($company) use ($rows, $present) {
            $companyRows = $rows->where('company', $company->companycode)
                ->map(fn ($r) => ['dept_id' => $r->department_id ?: 0, 'dept' => $r->department_name] + $present($r));

            $departments = $companyRows
                ->groupBy('dept_id')
                ->map(fn ($deptRows, $deptId) => [
                    'key'   => $company->companycode . '-' . $deptId,
                    'name'  => $deptRows->first()['dept'] ?: 'No department',
                    'count' => $deptRows->count(),
                    'due'   => $deptRows->where('canAck', true)->count(),
                    'rows'  => $deptRows->map(fn ($r) => array_diff_key($r, ['dept_id' => 0, 'dept' => 0]))->values(),
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            return [
                'code'        => $company->companycode,
                'name'        => $company->companyname,
                'count'       => $companyRows->count(),
                'due'         => $companyRows->where('canAck', true)->count(),
                'departments' => $departments,
            ];
        });
    }

    /**
     * Status, acknowledgement trail and row state shared by both pages.
     */
    private function applicationState(object $r): array
    {
        [$kind, $detail, $flagged] = $this->parseStatus($r->application_status);
        $acked = $this->hasAccounting($r->acknowledged);

        return [
            'appId'    => (int) $r->id,
            'status'   => $kind,
            'statusBy' => $detail,
            'flagged'  => $flagged,
            'acks'     => $this->acknowledgements($r->acknowledged, $r->it_acknowledged_by, $r->hr_acknowledged_by, $r->acc_acknowledged_by),
            'canAck'   => !$acked && $kind !== 'pending',
            // Legacy row colours: green once Accounting acknowledged, amber while waiting (except cancelled)
            'tone'     => $acked ? 'acked' : ($kind !== 'cancelled' ? 'due' : ''),
            'applied'  => $this->dateTime($r->datetime),
        ];
    }

    private function hasAccounting(?string $raw): bool
    {
        return str_contains((string) $raw, 'Accounting');
    }

    /**
     * "HR [Oct 21, 2026 - 1:51 PM], <br>Accounting [...]" → [['dept' => 'HR', 'by' => ..., 'time' => ...], ...]
     * Department matching follows the legacy page (IT, then HR, then Accounting).
     */
    private function acknowledgements(?string $raw, ?string $itBy, ?string $hrBy, ?string $accBy): array
    {
        $entries = preg_split('/,?\s*<br\s*\/?>\s*/i', trim((string) $raw));

        return collect($entries)
            ->map(fn ($e) => trim(strip_tags($e), " ,"))
            ->filter()
            ->map(function ($e) use ($itBy, $hrBy, $accBy) {
                [$dept, $by] = match (true) {
                    str_contains($e, 'IT')         => ['IT', $itBy],
                    str_contains($e, 'HR')         => ['HR', $hrBy],
                    str_contains($e, 'Accounting') => ['Accounting', $accBy],
                    default                        => [$e, null],
                };
                preg_match('/\[(.*?)\]/', $e, $m);

                return ['dept' => $dept, 'by' => trim((string) $by) ?: null, 'time' => $m[1] ?? null];
            })
            ->values()
            ->all();
    }

    /**
     * Accounting acknowledges one application: appends "Accounting [M j, Y - g:i A]"
     * to the trail and records the user's last name, exactly as the HRIS portal did.
     * Refuses pending, already-acknowledged, resigned-employee and unknown applications.
     */
    private function acknowledge(string $table, int $id): JsonResponse
    {
        $conn = $this->hris();

        $idno = trim((string) session('portal_idno'));
        $name = $idno !== '' ? trim((string) $conn->table('employee_profile')->where('idno', $idno)->value('lastname')) : '';
        if ($name === '') {
            return response()->json(['message' => 'Your HRIS account could not be identified. Log in again from HRIS.'], 403);
        }

        $codes = $this->companies()->pluck('companycode');

        return $conn->transaction(function () use ($conn, $table, $id, $name, $idno, $codes) {
            $row = $conn->table($table)
                ->where('id', $id)
                ->lockForUpdate()
                ->first(['id', 'idno', 'application_status', 'acknowledged', 'it_acknowledged_by', 'hr_acknowledged_by', 'acc_acknowledged_by']);

            $listed = $row && $conn->table('employee_details')
                ->where('idno', $row->idno)
                ->where('status', 'not like', '%RESIGNED%')
                ->whereIn('company', $codes)
                ->exists();
            if (!$listed) {
                return response()->json(['message' => 'Application not found.'], 404);
            }

            $trail = fn ($r) => $this->acknowledgements($r->acknowledged, $r->it_acknowledged_by, $r->hr_acknowledged_by, $r->acc_acknowledged_by);

            if ($this->hasAccounting($row->acknowledged)) {
                return response()->json(['message' => 'Accounting has already acknowledged this application.', 'acks' => $trail($row)], 409);
            }
            if ($this->parseStatus($row->application_status)[0] === 'pending') {
                return response()->json(['message' => 'Pending applications cannot be acknowledged yet.'], 422);
            }

            $entry = 'Accounting [' . now('Asia/Manila')->format('M j, Y - g:i A') . ']';
            $current = (string) $row->acknowledged;
            $ack = $current === '' ? $entry : $current . ', <br>' . $entry;

            $conn->table($table)->where('id', $id)->update([
                'acknowledged'        => $ack,
                'acc_acknowledged_by' => $name,
            ]);

            Log::info('HRIS application acknowledged by Accounting', ['table' => $table, 'id' => $id, 'by' => $idno]);

            $row->acknowledged = $ack;
            $row->acc_acknowledged_by = $name;

            return response()->json(['message' => 'Application acknowledged.', 'acks' => $trail($row)]);
        });
    }

    private function date(?string $value): ?string
    {
        return $value && !in_array($value, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($value)) : null;
    }

    private function dateTime(?string $value): ?string
    {
        return $value && !str_starts_with($value, '0000') ? date('M d, Y h:i A', strtotime($value)) : null;
    }
}
