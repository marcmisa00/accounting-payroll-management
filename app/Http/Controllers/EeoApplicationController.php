<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesHrisStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Read-only list of hris.emergencyearlyout (EEO), grouped company → department,
 * filtered by EEO date. Same page structure as LeaveApplicationController;
 * eeo.js draws each department's table and handles the search.
 */
class EeoApplicationController extends Controller
{
    use ParsesHrisStatus;

    private const HRIS_CONNECTION = 'hris';

    // Proof uploads live on the HRIS portal; only paths in this folder are linked.
    private const PROOF_PATTERN = '#^hris/employeeportal/companyfiles/eeo_proofs/[A-Za-z0-9_.-]+$#';

    public function index(Request $request): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $thisYear = (int) now('Asia/Manila')->year;

        // Years that have EEOs (ignoring obviously bad dates), always including this year.
        $years = $conn->table('emergencyearlyout')
            ->whereBetween('dateEEO', ['2000-01-01', ($thisYear + 1) . '-12-31'])
            ->selectRaw('DISTINCT YEAR(dateEEO) AS y')
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

        $rows = $from > $to ? collect() : $conn->table('emergencyearlyout as e')
            ->join('employee_details as ed', 'ed.idno', '=', 'e.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'e.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            ->whereBetween('e.dateEEO', [$from, $to])
            ->select(
                'e.id',
                'e.idno',
                'e.type_EEO',
                'e.dateEEO',
                'e.timeEEO',
                'e.reason',
                'e.proof_path',
                'e.eeo_status',
                'e.approvers_remarks',
                'e.acknowledged',
                'ep.lastname',
                'ep.firstname',
                'ed.company',
                'ed.department as department_id',
                'd.department as department_name'
            )
            ->orderByDesc('e.dateEEO')
            ->orderByDesc('e.timeEEO')
            ->orderByDesc('e.id')
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

        return view('leaves.eeo', [
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
        // "HR Approved - [date]" is EEO-only wording; parse it as "Approved" and keep the label.
        $status = trim((string) $r->eeo_status);
        $hr = (bool) preg_match('/^\*?\s*HR\s+Approved/i', $status);
        if ($hr) {
            $status = preg_replace('/^(\*?\s*)HR\s+/i', '$1', $status);
        }

        [$kind, $detail, $flagged] = $this->parseStatus($status);

        return [
            'id'          => (string) $r->idno,
            'name'        => trim(($r->lastname ?? '') . ', ' . ($r->firstname ?? ''), ', ') ?: (string) $r->idno,
            'type'        => $r->type_EEO ?: '—',
            'date'        => ($r->dateEEO && $r->dateEEO !== '0000-00-00') ? date('M d, Y', strtotime($r->dateEEO)) : null,
            'time'        => $r->timeEEO ? date('h:i A', strtotime($r->timeEEO)) : null,
            'reason'      => $r->reason,
            'proof'       => $this->proof($r->proof_path),
            'status'      => $kind,
            'statusLabel' => $hr ? 'HR Approved' : null,
            'statusBy'    => $detail,
            'flagged'     => $flagged,
            'apNotes'     => $r->approvers_remarks,
            'acknowledged' => $this->acknowledged($r->acknowledged),
        ];
    }

    /**
     * Link to the proof file on the HRIS portal, with the upload time taken from
     * its name (EEO_<idno>_<unix time>.ext), as the legacy page shows it.
     */
    private function proof(?string $path): ?array
    {
        $path = trim((string) $path);
        if (! preg_match(self::PROOF_PATTERN, $path)) {
            return null;
        }

        $parts = explode('_', pathinfo($path, PATHINFO_FILENAME));
        $uploaded = isset($parts[2]) && ctype_digit($parts[2])
            ? now('Asia/Manila')->setTimestamp((int) $parts[2])->format('M d, Y h:i A')
            : null;

        return [
            'url'      => rtrim(config('app.hris_url'), '/') . '/' . $path,
            'uploaded' => $uploaded,
        ];
    }

    /**
     * "Birondo (Team Manager) [Feb 18, 2025 - 5:31 AM], <br>Abril (Team Leader \n) [...]"
     *   → ['Birondo (Team Manager) · Feb 18, 2025 - 5:31 AM', 'Abril (Team Leader) · ...']
     */
    private function acknowledged(?string $raw): array
    {
        $entries = preg_split('/,?\s*<br\s*\/?>\s*/i', (string) $raw);

        return collect($entries)
            ->map(function ($entry) {
                $entry = trim(preg_replace('/\s+/u', ' ', strip_tags($entry)), " ,");
                $entry = preg_replace('/\s+\)/', ')', $entry);

                return preg_replace('/\s*\[(.+)\]$/', ' · $1', $entry);
            })
            ->filter()
            ->values()
            ->all();
    }
}
