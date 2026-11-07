<?php

namespace App\Services;

use App\Helpers\ShiftHelper;
use Carbon\CarbonPeriod;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Rows of the Attendance Summary (absences / late): one cell per day with the
 * day's remarks, plus incident (A-F) and leave totals per employee.
 * Based on attendancemonitoringsummary.php on the main site, read only:
 * the old page rewrote remarks / previousRemarks and turned leaves with
 * logs into P every time it was opened. Shared by the page and the export.
 */
class AttendanceSummaryReport
{
    /** Infraction codes counted in the Summary columns. */
    public const INCIDENTS = ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E', 'f' => 'F'];

    /** Totals columns, in the old page's order. */
    public const TOTALS = [
        'p' => 'P', 'ci' => 'CI', 'pto' => 'PTO', 'vl' => 'VL', 'sl' => 'SL', 'blp' => 'BLP', 'eo' => 'EO', 'spl' => 'SPL',
        'ltl' => 'LTL', 'mtl' => 'MTL', 'ptl' => 'PTL', 'sus' => 'SUS', 'awol' => 'AWOL', 'bl' => 'BL', 'mdl' => 'MDL',
    ];

    /** Department column colours, as on the old page (others stay uncoloured). */
    public const DEPT_COLORS = [
        'Admin' => '#e89a95', 'HR' => '#b4a7d5', 'IT' => '#d8ead2', 'Management' => '#dd7e6a',
        'Home Health' => '#dad2e9', 'HH - Medicare' => '#d1e0e3', 'HP - Medicare' => '#c9daf7',
        'HP - Managed Care' => '#cce2f6', 'HH - Managed Care' => '#e1d8f4', 'Data Review' => '#87ceea',
        'PFCPD' => '#D8BFD8', 'Anaheim Billers' => '#b4a7d3', 'Hospice' => '#dd7e6a', 'Miracle' => '#ebd1dc',
        'HH Digos' => '#cfe2f3', 'Care Coordinator' => '#f9ca9c', 'Payment Posting' => '#a2c3c8',
        'Intake and Support' => '#ffe59a', 'DPD and HR' => '#b6d7a8', 'Virtual Assistant' => '#d5a6be',
    ];

    /** Incident legend of the old page's header: [incident, points, code]. */
    public const LEGEND = [
        [['Absent w/ proper CI, w/ Med Cert', 'No Points', 'As is'], ['Late w/in 15 minutes', '0.2', 'TIME-D']],
        [['Absent w/ proper CI', '0.2', 'CI-A'], ['Late 16 mins and up w/ CI', '0.3', 'TIME-E']],
        [['Absent w/ proper CI but invalid reason', '1.0', 'CI-B'], ['Late w/o CI (16mins and up)', '0.5', 'TIME-F']],
        [['Absent w/ CI w/in 16 mins up to 2 hrs from start of shift', '0.5', 'CI-C'], null],
    ];

    /** Day colours: the Employee Logs tones, plus a day with two records (second shift). */
    public const TONES = EmployeeLogsReport::TONES + ['split' => 'Two records'];

    /** Remarks that count one total by themselves. */
    private const TYPE_COUNTER = [
        'P' => 'p', 'P-TD' => 'p', 'CI' => 'ci', 'SUS' => 'sus', 'EO' => 'eo',
        'PTO' => 'pto', 'VL' => 'vl', 'SL' => 'sl', 'BLP' => 'blp', 'SPL' => 'spl', 'LTL' => 'ltl', 'MTL' => 'mtl',
        'PTL' => 'ptl', 'BL' => 'bl', 'MDL' => 'mdl', 'AWOL' => 'awol', 'AWOL2' => 'awol',
    ];

    /** Infraction codes that are written after a login time ("05:01-D") or as "CI-D" when there is none. */
    private const TIMED_CODES = ['D', 'E', 'F', 'NC', 'GS', 'IO', 'PcP', 'PO', 'TI', 'TP'];

    public function __construct(private DatabaseManager $db) {}

    /**
     * Employees in scope with their records by date and their totals. Resigned
     * employees are kept when they resigned on or after the start date.
     *
     * @param  array<int>  $departments  empty = all
     */
    public function employees(string $company, array $departments, string $startDate, string $endDate): Collection
    {
        $employees = $this->roster($company, $departments, $startDate);
        if ($employees->isEmpty()) {
            return $employees;
        }

        // Plain idno (not TRIM(idno)) so the idno + logindate index is used. MySQL also matches
        // ids saved with a trailing space (e.g. "103081 "), so they are trimmed when grouped.
        $records = $employees->pluck('idno')->map(fn ($id) => (string) $id)->chunk(500)
            ->flatMap(fn ($chunk) => $this->db->connection('hris')->table('attendance')
                ->select('idno', 'logindate', 'loginam', 'status', 'remarks', 'previousRemarks')
                ->whereIn('idno', $chunk->values()->all())
                ->whereBetween('logindate', [$startDate, $endDate])
                ->orderBy('id')
                ->get())
            ->groupBy([fn ($a) => trim((string) $a->idno), 'logindate']);

        foreach ($employees as $emp) {
            $emp->records = $records->get(trim((string) $emp->idno), collect());
            $emp->totals = $this->totals($emp->records);
        }

        return $employees;
    }

    /**
     * Employees in scope (department ID, then name), with the row flags the
     * summary pages share: isGy, isAm, isFixed, deptColor, isResigned, given, name.
     * Resigned employees are kept when they resigned on or after the start date.
     * Also used by the Missed Logs / OB Summary.
     *
     * @param  array<int>  $departments  empty = all
     */
    public function roster(string $company, array $departments, string $startDate): Collection
    {
        $conn = $this->db->connection('hris');

        // Some employees have several resignation rows: the latest one counts (the old join listed them twice).
        $resigned = $conn->table('resignation')->selectRaw('idno, MAX(dateresigned) as dateresigned')->groupBy('idno');

        $employees = $conn->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->leftJoin('employee_payroll as pr', 'pr.idno', '=', 'ep.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->leftJoinSub($resigned, 'r', 'r.idno', '=', 'ep.idno')
            ->select(
                'ep.idno', 'ep.lastname', 'ep.firstname', 'ep.suffix',
                'ed.startshift', 'ed.endshift', 'ed.status as emp_status',
                'pr.salary_type', 'd.department as dept_name', 'r.dateresigned',
            )
            ->where(fn ($q) => $q->where('ed.status', 'NOT LIKE', '%RESIGNED%')
                ->orWhere(fn ($q) => $q->whereNull('r.dateresigned')->orWhere('r.dateresigned', '>=', $startDate)))
            ->when($company !== '', fn ($q) => $q->where('ed.company', $company))
            ->when($departments !== [], fn ($q) => $q->whereIn('ed.department', $departments))
            ->orderBy('d.id')
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->get();

        foreach ($employees as $emp) {
            $emp->isGy = ShiftHelper::isGyShift($emp->startshift);
            // Morning: any other set start time (the old page's "AM" shift filter).
            $emp->isAm = ! $emp->isGy && ! in_array($emp->startshift, [null, ''], true);
            $emp->isFixed = in_array($emp->salary_type, ['Fixed', 'Daily'], true);
            $emp->deptColor = self::DEPT_COLORS[(string) $emp->dept_name] ?? null;
            $emp->isResigned = str_contains(strtoupper((string) $emp->emp_status), 'RESIGNED');
            $emp->given = $emp->firstname.(in_array($emp->suffix, [null, '', 'N/A'], true) ? '' : ' '.$emp->suffix);
            $emp->name = $emp->lastname.', '.$emp->given;
        }

        return $employees;
    }

    /**
     * Search (every word in the name or ID) and the morning / graveyard / fixed
     * checkboxes, which work like Employee Logs: any ticked kind is shown.
     */
    public static function filter(Collection $employees, string $search = '', bool $onlyFixed = false, bool $onlyGy = false, bool $onlyAm = false): Collection
    {
        $words = preg_split('/\s+/', strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY);

        return $employees->filter(function ($emp) use ($words, $onlyFixed, $onlyGy, $onlyAm) {
            if (($onlyFixed || $onlyGy || $onlyAm)
                && ! (($onlyFixed && $emp->isFixed) || ($onlyGy && $emp->isGy) || ($onlyAm && $emp->isAm))) {
                return false;
            }
            $haystack = strtolower($emp->lastname.' '.$emp->firstname.' '.$emp->idno);

            return collect($words)->every(fn ($w) => str_contains($haystack, $w));
        })->values();
    }

    /**
     * The range's days, with where a new month starts.
     *
     * @return array<int, object{date: string, day: string, weekday: string, month: string, monthShort: string, monthStart: bool, sunday: bool}>
     */
    public static function dates(string $startDate, string $endDate): array
    {
        $dates = [];
        $prev = null;
        foreach (CarbonPeriod::create($startDate, $endDate) as $d) {
            $dates[] = (object) [
                'date' => $d->toDateString(),
                'day' => $d->format('j'),
                'weekday' => $d->format('D'),
                'month' => $d->format('F Y'),
                'monthShort' => $d->format('M'),
                'monthStart' => $prev !== null && $prev !== $d->format('Y-m'),
                'sunday' => $d->isSunday(),
            ];
            $prev = $d->format('Y-m');
        }

        return $dates;
    }

    /**
     * One cell per date (null when there is no record). Two records on a day
     * (second shift) are shown together, as on the old page.
     *
     * @param  array<object>  $dates  from dates()
     * @return array<string, object{text: string, tone: string, tip: string}|null>
     */
    public function cells(object $emp, array $dates): array
    {
        $cells = [];
        foreach ($dates as $d) {
            $rows = $emp->records->get($d->date);
            if (! $rows || $rows->isEmpty()) {
                $cells[$d->date] = null;

                continue;
            }

            $texts = $rows->map(fn ($r) => self::label((string) $r->remarks, $r->loginam));
            $tip = $rows->map(function ($r) use ($texts) {
                $previous = trim((string) $r->previousRemarks);

                return 'Status: '.($r->status ?: 'none').($previous !== '' && ! $texts->contains($previous) ? ' · Previous: '.$previous : '');
            });

            $cells[$d->date] = (object) [
                'text' => $texts->filter(fn ($t) => $t !== '')->join(' - ') ?: '—',
                'tone' => $rows->count() > 1 ? 'split' : EmployeeLogsReport::tone((string) $rows[0]->status, (string) $rows[0]->remarks),
                'tip' => date('D, M j', strtotime($d->date)).' · '.$tip->join(' | '),
            ];
        }

        return $cells;
    }

    /**
     * Remarks as shown: bare codes left by older records get their full form
     * ("A" -> "CI-A", "D" -> "05:01-D" or "CI-D"), like the old page, without
     * saving it. The infraction screens already save the full form.
     */
    public static function label(string $remarks, ?string $loginam): string
    {
        if (in_array($remarks, ['A', 'B', 'C'], true)) {
            return 'CI-'.$remarks;
        }
        if (in_array($remarks, self::TIMED_CODES, true)) {
            $ts = in_array($loginam, [null, '', '0', '00:00:00'], true) ? false : strtotime($loginam);

            return ($ts === false ? 'CI' : date('h:i', $ts)).'-'.$remarks;
        }

        return $remarks;
    }

    /**
     * Totals a remarks value adds to, e.g. "SL-A" -> sl + a, "CI-B" -> b + ci,
     * "05:02-D/EO" -> d + eo, "P/EO" -> eo.
     *
     * @return array<string>
     */
    public static function counters(string $remarks): array
    {
        static $cache = [];

        return $cache[$remarks] ??= self::classify($remarks);
    }

    /** @return array<string, int> */
    private function totals(Collection $byDate): array
    {
        $totals = array_fill_keys([...array_keys(self::INCIDENTS), ...array_keys(self::TOTALS)], 0);
        foreach ($byDate as $rows) {
            foreach ($rows as $r) {
                foreach (self::counters((string) $r->remarks) as $key) {
                    $totals[$key]++;
                }
            }
        }

        return $totals;
    }

    /** @return array<string> */
    private static function classify(string $remarks): array
    {
        $parts = explode('/', trim($remarks));
        $main = $parts[0];
        $earlyOut = collect(array_slice($parts, 1))->contains(fn ($p) => $p === 'EO' || str_starts_with($p, 'EEO'));

        // Present with an early out counts as an early out only (P/EO, P/EEO).
        if ($main === 'P' && $earlyOut) {
            return ['eo'];
        }

        $out = match (true) {
            (bool) preg_match('/^SL-([ABC])$/', $main, $m) => ['sl', strtolower($m[1])],
            str_starts_with($main, 'SL-') => ['sl'],
            (bool) preg_match('/(^|-)([DEF])$/', $main, $m) => [strtolower($m[2])],
            (bool) preg_match('/^(CI-)?([ABC])$/', $main, $m) => [strtolower($m[2]), 'ci'],
            // EEO-PO, EEO-IO...: the old page matched "-PO" first and never counted these.
            str_starts_with($main, 'EEO') => ['eo'],
            // Called-in absences with a reason code (CI-NC, CI-IO, CI-PO...).
            str_starts_with($main, 'CI-') => ['ci'],
            default => isset(self::TYPE_COUNTER[$main]) ? [self::TYPE_COUNTER[$main]] : [],
        };

        // An early out after another type ("05:02-D/EO", "SL/EEO-IO").
        if ($earlyOut) {
            $out[] = 'eo';
        }

        return array_values(array_unique($out));
    }
}
