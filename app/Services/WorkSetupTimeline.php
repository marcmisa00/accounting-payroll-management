<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Work setup (OS / WFH / Hybrid) that applied on a given date, so OS/WFH pay
 * rules follow the day worked and not today's employee_details.location.
 *
 * Port of HRIS "LOCATION BY DATE" (hris/employeeportal/attendance_helpers.php:
 * loadEmployeeLocationEvents, buildEmployeeLocationTimeline,
 * resolveLocationFromTimeline). Keep the two in step: payroll and the HRIS
 * attendance page must agree on an employee's setup for each day.
 *
 * Sources: approved wfh_application rows, approved work_transfer rows and
 * logged manual edits in hr_audit_log. Cancelled, disapproved and pending
 * rows are ignored. Read only.
 */
class WorkSetupTimeline
{
    private const HRIS_CONNECTION = 'hris';

    private const NO_END = '9999-12-31';

    /**
     * @return array{base: array, temp: array, current: string}
     */
    public function forEmployee(string $idno, object $employeeDetails, ?string $today = null): array
    {
        return $this->build(
            $this->loadEvents(trim($idno)),
            (string) ($employeeDetails->location ?? ''),
            isset($employeeDetails->updatedby) ? (string) $employeeDetails->updatedby : null,
            isset($employeeDetails->updateddatetime) ? (string) $employeeDetails->updateddatetime : null,
            $today ?? now('Asia/Manila')->toDateString()
        );
    }

    /**
     * @return array{location: string, source: string}
     */
    public function resolve(array $timeline, string $date): array
    {
        $active = null;
        foreach ($timeline['base'] as $entry) {
            if ($date >= $entry['start'] && $date <= $entry['end']) {
                $active = $entry;
            }
        }
        foreach ($timeline['temp'] as $entry) {
            if ($date >= $entry['start'] && $date <= $entry['end'] && ($active === null || $entry['start'] >= $active['start'])) {
                $active = $entry;
            }
        }
        if ($active !== null) {
            return ['location' => $active['location'], 'source' => $active['source']];
        }

        $last = null;
        $next = null;
        foreach (array_merge($timeline['base'], $timeline['temp']) as $entry) {
            if ($entry['end'] < $date && ($last === null || $entry['end'] > $last['end'])) {
                $last = $entry;
            }
            if ($entry['start'] > $date && ($next === null || $entry['start'] < $next['start'])) {
                $next = $entry;
            }
        }
        if ($last !== null) {
            return ['location' => $last['after'], 'source' => 'after_'.$last['source']];
        }
        if ($next !== null) {
            return ['location' => $next['before'], 'source' => 'before_'.$next['source']];
        }

        return ['location' => $timeline['current'], 'source' => 'employee_details_default'];
    }

    private function loadEvents(string $idno): array
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $events = ['wfh' => [], 'transfer' => [], 'manual' => []];

        $queries = [
            'wfh' => fn () => $conn->table('wfh_application')
                ->select('id', 'date_effective', 'end_date', 'hso_type')
                ->where('idno', $idno)
                ->where('application_status', 'like', 'Approved%')
                ->whereNotNull('date_effective')
                ->where('date_effective', '>', '1000-01-01'),
            'transfer' => fn () => $conn->table('work_transfer')
                ->select('id', 'date_transfer', 'end_date', 'prev_loc')
                ->whereRaw('TRIM(idno) = ?', [$idno])
                ->where('application_status', 'like', 'Approved%')
                ->whereNotNull('date_transfer')
                ->where('date_transfer', '>', '1000-01-01'),
            'manual' => fn () => $conn->table('hr_audit_log')
                ->selectRaw("id, DATE(edit_datetime) AS change_date,
                    JSON_UNQUOTE(JSON_EXTRACT(changes_json, '$.location.old_value')) AS old_value,
                    JSON_UNQUOTE(JSON_EXTRACT(changes_json, '$.location.new_value')) AS new_value")
                ->where('employee_idno', $idno)
                ->where('section', 'Employee Details')
                ->whereRaw("JSON_EXTRACT(changes_json, '$.location') IS NOT NULL"),
        ];

        foreach ($queries as $key => $query) {
            try {
                $events[$key] = $query()->get()->map(fn ($r) => (array) $r)->all();
            } catch (Throwable $e) {
                Log::warning("WorkSetupTimeline: location events ($key) failed for $idno: ".$e->getMessage());
            }
        }

        return $events;
    }

    private function build(array $events, string $currentLocation, ?string $updatedBy, ?string $updatedAt, string $today): array
    {
        $base = []; // setups that hold until replaced
        $temp = []; // temporary transfers laid over the base setup

        foreach ($events['wfh'] as $wfh) {
            $base[] = ['start' => $wfh['date_effective'], 'end' => $wfh['end_date'] ?: self::NO_END,
                'location' => ((int) $wfh['hso_type'] === 1) ? 'Hybrid' : 'WFH', 'source' => 'wfh_application',
                'rank' => 0, 'id' => (int) $wfh['id'], 'before' => 'OS', 'after' => 'OS'];
        }
        foreach ($events['transfer'] as $transfer) {
            $previous = $this->setupFromPrevLoc($transfer['prev_loc']) ?: 'OS';
            $entry = ['start' => $transfer['date_transfer'], 'end' => $transfer['end_date'] ?: self::NO_END,
                'location' => 'OS', 'source' => 'work_transfer',
                'rank' => 1, 'id' => (int) $transfer['id'], 'before' => $previous, 'after' => $previous];
            if ($transfer['end_date']) {
                $temp[] = $entry;
            } else {
                $entry['after'] = 'OS';
                $base[] = $entry;
            }
        }
        foreach ($events['manual'] as $manual) {
            if ($manual['new_value'] !== null && $manual['new_value'] !== '') {
                $base[] = ['start' => $manual['change_date'], 'end' => self::NO_END,
                    'location' => $manual['new_value'], 'source' => 'manual_change',
                    'rank' => 2, 'id' => (int) $manual['id'], 'before' => $manual['old_value'] ?: 'OS', 'after' => 'OS'];
            }
        }

        $timeline = ['base' => $this->sortAndTrim($base), 'temp' => $this->sortAndTrim($temp), 'current' => $currentLocation];

        // employee_details can show a setup the rows above cannot explain: an HR edit from before
        // hr_audit_log existed, or the cron applying an approved filing late. Add it from the date
        // employee_details was last updated. A filing that ended in the last 3 days is only the
        // cron's normal revert delay, so that case is left alone.
        if (! empty($base) || ! empty($temp)) {
            $resolvedToday = $this->resolve($timeline, $today)['location'];
            $updatedDate = $updatedAt ? substr($updatedAt, 0, 10) : null;
            if ($resolvedToday !== $currentLocation && $updatedDate && $updatedDate > '1000-01-01' && $updatedDate <= $today) {
                $latestStart = '0000-00-00';
                $endedRecently = false;
                $recentLimit = date('Y-m-d', strtotime($today.' -3 days'));
                foreach (array_merge($timeline['base'], $timeline['temp']) as $entry) {
                    if ($entry['start'] > $latestStart) {
                        $latestStart = $entry['start'];
                    }
                    if ($entry['end'] >= $recentLimit && $entry['end'] < $today) {
                        $endedRecently = true;
                    }
                }
                if ($updatedDate > $latestStart && ! $endedRecently) {
                    $base[] = ['start' => $updatedDate, 'end' => self::NO_END, 'location' => $currentLocation,
                        'source' => ($updatedBy === 'System') ? 'system_applied_late' : 'manual_change_unlogged',
                        'rank' => 2, 'id' => 0, 'before' => $resolvedToday, 'after' => 'OS'];
                    $timeline['base'] = $this->sortAndTrim($base);
                }
            }
        }

        return $timeline;
    }

    private function setupFromPrevLoc(?string $prevLoc): ?string
    {
        if ($prevLoc === null || trim($prevLoc) === '') {
            return null;
        }
        if (str_contains($prevLoc, '-')) {
            $setup = trim(explode('-', $prevLoc, 2)[1]);

            return $setup !== '' ? $setup : null;
        }

        return 'OS'; // prev_loc holding only a branch name means on-site
    }

    private function sortAndTrim(array $entries): array
    {
        usort($entries, fn ($a, $b) => [$a['start'], $a['rank'], $a['id']] <=> [$b['start'], $b['rank'], $b['id']]);

        $count = count($entries);
        for ($i = 0; $i < $count - 1; $i++) {
            $dayBeforeNext = date('Y-m-d', strtotime($entries[$i + 1]['start'].' -1 day'));
            if ($entries[$i]['end'] > $dayBeforeNext) {
                $entries[$i]['end'] = $dayBeforeNext;
            }
        }

        return array_values(array_filter($entries, fn ($entry) => $entry['end'] >= $entry['start']));
    }
}
