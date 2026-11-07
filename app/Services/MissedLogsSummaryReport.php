<?php

namespace App\Services;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Rows of the Missed Logs / OB Summary: one cell per day with the day's
 * lunch / missed-login infraction codes, plus per-code totals and points.
 * Based on attendancemonitoringsummarymissed.php on the main site, with
 * these fixes:
 *
 *  - Codes come from the recorded offense, not attendance.remarks1, so old
 *    rows ("Code L", "N") and new rows ("L", "L-") read the same, and two
 *    infractions on one day are both shown (the old page kept the last one).
 *  - A day with two time records (second shift) no longer counts its
 *    infractions twice.
 *  - The empty Total / Freq(-) columns are replaced by Total (incidents)
 *    and Points.
 *
 * Employees, search and shift filters are the Attendance Summary's.
 */
class MissedLogsSummaryReport
{
    /** Offense id => code, in the totals columns' order. */
    public const CODES = [66 => 'M', 19 => 'L', 65 => 'B-', 22 => 'I-', 63 => 'L-'];

    /** What each code means (column and total titles). */
    public const MEANING = [
        'M' => 'Missed first login (no form) and missed lunch in/out',
        'L' => 'Over-break (lunch)',
        'B-' => 'Missed first login (no form) and over-break',
        'I-' => 'Missed first login (no form)',
        'L-' => 'Missed lunch in/out, non-work reason (formerly N)',
    ];

    /** Incident tagging reference in the page header: [incident, points, code]. */
    public const LEGEND = [
        [['Over-break (lunch)', '0.2', 'L'], ['Forgot to clock in (first shift) and failed to submit form and Over-break', '0.4', 'B-']],
        [['Forgot to clock in/out (Lunch) w/ non-work related reason', '0.2', 'L-'], ['Forgot to clock in (first shift) and failed to submit form and Missed Out/In (Lunch)', '0.4', 'M']],
        [['Forgot to clock in (first shift) and failed to submit form', '0.2', 'I-'], null],
    ];

    public function __construct(private DatabaseManager $db, private AttendanceSummaryReport $summary) {}

    /**
     * Employees in scope with their infractions by date and their totals.
     *
     * @param  array<int>  $departments  empty = all
     */
    public function employees(string $company, array $departments, string $startDate, string $endDate): Collection
    {
        $employees = $this->summary->roster($company, $departments, $startDate);
        if ($employees->isEmpty()) {
            return $employees;
        }

        $conn = $this->db->connection('hris');
        $ids = $employees->pluck('idno')->map(fn ($id) => (string) $id);

        $points = $ids->chunk(500)
            ->flatMap(fn ($chunk) => $conn->table('points')
                ->select('idno', 'logindate', 'offense', 'points')
                ->whereIn('idno', $chunk->values()->all())
                ->whereBetween('logindate', [$startDate, $endDate])
                ->whereIn('offense', array_map('strval', array_keys(self::CODES)))
                ->orderBy('id')
                ->get())
            ->groupBy([fn ($p) => trim((string) $p->idno), 'logindate']);

        // Lunch times for the tooltips, only for days with an infraction.
        $withPoints = $points->keys()->all();
        $lunch = collect($withPoints)->chunk(500)
            ->flatMap(fn ($chunk) => $conn->table('attendance')
                ->select('idno', 'logindate', 'logoutam', 'loginpm')
                ->whereIn('idno', $chunk->values()->all())
                ->whereBetween('logindate', [$startDate, $endDate])
                ->orderBy('id')
                ->get())
            ->groupBy([fn ($a) => trim((string) $a->idno), 'logindate']);

        foreach ($employees as $emp) {
            $emp->infractions = $points->get(trim((string) $emp->idno), collect());
            $emp->lunch = $lunch->get(trim((string) $emp->idno), collect());
            $emp->totals = $this->totals($emp->infractions);
        }

        return $employees;
    }

    /**
     * One cell per date (null when there is no infraction).
     *
     * @param  array<object>  $dates  from AttendanceSummaryReport::dates()
     * @return array<string, object{text: string, tone: string, tip: array<array{0: string, 1: string}>}|null>
     */
    public function cells(object $emp, array $dates): array
    {
        $cells = [];
        foreach ($dates as $d) {
            $rows = $emp->infractions->get($d->date);
            if (! $rows || $rows->isEmpty()) {
                $cells[$d->date] = null;

                continue;
            }

            $codes = self::codesOf($rows);
            // Hover: lunch out, lunch in and points, one [label, value] per line.
            $tip = [];
            foreach ($emp->lunch->get($d->date, collect()) as $a) {
                $tip[] = ['Lunch out', self::time($a->logoutam) ?? 'None'];
                $tip[] = ['Lunch in', self::time($a->loginpm) ?? 'None'];
            }
            $tip[] = ['Points', self::points((float) $rows->sum('points'))];

            $cells[$d->date] = (object) [
                'text' => implode(' ', $codes),
                'tone' => $codes === ['L'] ? 'ob' : 'missed',
                'tip' => $tip,
            ];
        }

        return $cells;
    }

    /** Points as shown: "0.2", "1", "0.45". */
    public static function points(float $points): string
    {
        return rtrim(rtrim(number_format($points, 2, '.', ''), '0'), '.');
    }

    /** @return array<string> codes of a day's points rows, in column order, once each */
    private static function codesOf(Collection $rows): array
    {
        $ids = $rows->pluck('offense')->map(fn ($o) => (int) $o)->all();

        return array_values(array_filter(self::CODES, fn ($id) => in_array($id, $ids, true), ARRAY_FILTER_USE_KEY));
    }

    /** @return array<string, int|float> code => count, plus total and points */
    private function totals(Collection $byDate): array
    {
        $totals = array_fill_keys(array_values(self::CODES), 0) + ['total' => 0, 'points' => 0.0];
        foreach ($byDate as $rows) {
            foreach ($rows as $p) {
                $totals[self::CODES[(int) $p->offense]]++;
                $totals['total']++;
                $totals['points'] += (float) $p->points;
            }
        }

        return $totals;
    }

    private static function time(?string $value): ?string
    {
        if (in_array($value, [null, '', '0', '00:00', '00:00:00'], true)) {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : date('h:i A', $ts);
    }
}
