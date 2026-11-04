<?php

namespace App\Services;

use App\Helpers\ShiftHelper;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Rows of the Employee Logs report: active employees with their time
 * records, late / system-login / adjusted flags and the day's infraction.
 * Shared by the page and the Excel export so both show the same thing.
 */
class EmployeeLogsReport
{
    public const TIME_FIELDS = ['loginam' => 'Login', 'logoutam' => 'Lunch out', 'loginpm' => 'Lunch in', 'logoutpm' => 'Logout'];

    /** Remarks colour by the day's status, shown in the legend in this order. */
    public const TONES = [
        'work' => 'Work / RD',
        'leave' => 'Leave / early out',
        'alert' => 'Late / resigned',
        'muted' => 'Absent / suspended',
    ];

    public function __construct(
        private DatabaseManager $db,
        private TimeAdjustmentMarkers $adjustments,
    ) {}

    /**
     * @param  array<int>  $departments  departments picked on the Attendance page (empty = all)
     * @param  int|null  $department  one of them (a tab), or null for all picked
     */
    public function employees(string $company, array $departments, ?int $department, string $startDate, string $endDate): Collection
    {
        $conn = $this->db->connection('hris');

        $employees = $conn->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->leftJoin('employee_payroll as pr', 'pr.idno', '=', 'ep.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->select(
                'ep.idno', 'ep.lastname', 'ep.firstname',
                'ed.startshift', 'ed.endshift', 'ed.location',
                'pr.salary_type', 'd.department as dept_name',
            )
            ->where('ed.status', 'NOT LIKE', '%RESIGNED%')
            ->when($company !== '', fn ($q) => $q->where('ed.company', $company))
            ->when($department !== null, fn ($q) => $q->where('ed.department', $department))
            ->when($department === null && $departments !== [], fn ($q) => $q->whereIn('ed.department', $departments))
            ->orderBy('ep.lastname')
            ->orderBy('ep.firstname')
            ->get();

        if ($employees->isEmpty()) {
            return $employees;
        }

        $ids = $employees->pluck('idno')->map(fn ($id) => (string) $id)->all();

        $attendance = collect($ids)->chunk(500)->flatMap(fn ($chunk) => $conn->table('attendance')
            ->select('id', 'idno', 'logindate', 'loginam', 'logoutam', 'loginpm', 'logoutpm', 'status', 'remarks', 'remarks1', 'addedby', 'shiftchange')
            ->whereIn('idno', $chunk->values()->all())
            ->whereBetween('logindate', [$startDate, $endDate])
            ->orderBy('logindate')
            ->orderBy('id')
            ->get());

        // Latest infraction per employee and day (as the old page showed).
        $points = collect($ids)->chunk(500)->flatMap(fn ($chunk) => $conn->table('points as p')
            ->leftJoin('offense as o', 'o.id', '=', 'p.offense')
            ->select('p.id', 'p.idno', 'p.logindate', 'p.points', 'o.title', 'o.description')
            ->whereIn('p.idno', $chunk->values()->all())
            ->whereBetween('p.logindate', [$startDate, $endDate])
            ->orderByDesc('p.id')
            ->get())
            ->unique(fn ($p) => $p->idno.'|'.$p->logindate)
            ->keyBy(fn ($p) => $p->idno.'|'.$p->logindate);

        $markers = $this->adjustments->forAttendance($attendance->pluck('id')->all());
        $byEmployee = $attendance->groupBy(fn ($a) => (string) $a->idno);

        foreach ($employees as $emp) {
            $emp->isGy = ShiftHelper::isGyShift($emp->startshift);
            $emp->isFixed = in_array($emp->salary_type, ['Fixed', 'Daily'], true);
            $emp->shiftLabel = $this->clock($emp->startshift, 'h:i A').' – '.$this->clock($emp->endshift, 'h:i A');
            $emp->days = ($byEmployee[(string) $emp->idno] ?? collect())
                ->map(fn ($att) => $this->day($emp, $att, $markers[$att->id] ?? [], $points->get($att->idno.'|'.$att->logindate)))
                ->values();
        }

        return $employees;
    }

    /**
     * Search (every word in the name or ID) and the legend checkboxes, which
     * work like the Employees page: either ticked kind is shown.
     */
    public static function filter(Collection $employees, string $search = '', bool $onlyFixed = false, bool $onlyGy = false): Collection
    {
        $words = preg_split('/\s+/', strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY);

        return $employees->filter(function ($emp) use ($words, $onlyFixed, $onlyGy) {
            if (($onlyFixed || $onlyGy) && ! (($onlyFixed && $emp->isFixed) || ($onlyGy && $emp->isGy))) {
                return false;
            }
            $haystack = strtolower($emp->lastname.' '.$emp->firstname.' '.$emp->idno);

            return collect($words)->every(fn ($w) => str_contains($haystack, $w));
        })->values();
    }

    private function day(object $emp, object $att, array $adjusted, ?object $point): object
    {
        // Pure leave days show zero times; part-day leave (work/leave) keeps its real logs.
        $statusParts = explode('/', strtolower((string) $att->status));
        $isLeave = in_array('leave', $statusParts, true) && ! in_array('work', $statusParts, true);

        $times = [];
        foreach (array_keys(self::TIME_FIELDS) as $field) {
            $value = $att->{$field};
            $empty = $isLeave || in_array($value, [null, '', '0', '00:00:00', '00:00'], true) || strtotime((string) $value) === false;

            $times[$field] = (object) [
                'text' => $empty ? ($isLeave || $field === 'loginam' ? '00:00:00' : '') : $this->clock($value, 'h:i:s A'),
                'empty' => $empty,
                'adjusted' => $isLeave ? null : ($adjusted[$field] ?? null),
            ];
        }

        // A shift change on the day (attendance.shiftchange) replaces the usual shift.
        $dayShift = $this->parseShift($att->shiftchange);
        $times['loginam']->late = ! $times['loginam']->empty && $this->isLate($dayShift['start'] ?? $emp->startshift, $att->loginam);
        $times['loginam']->system = ! $times['loginam']->empty && stripos((string) $att->addedby, 'SYSTEM') !== false;

        return (object) [
            'id' => $att->id,
            'logindate' => $att->logindate,
            'dateLabel' => date('M d, Y', strtotime($att->logindate)),
            'weekday' => date('D', strtotime($att->logindate)),
            'isLeave' => $isLeave,
            'shiftChange' => $dayShift ? $this->clock($dayShift['start'], 'h:i A').' – '.$this->clock($dayShift['end'], 'h:i A') : null,
            'tone' => self::tone((string) $att->status, (string) $att->remarks),
            'status' => (string) $att->status,
            'remarks' => implode(' · ', array_filter([$att->remarks, $att->remarks1], fn ($r) => $r !== null && $r !== '')),
            'times' => $times,
            'point' => $point ? (object) [
                'id' => $point->id,
                'code' => $point->title ? trim(str_replace('Attendance Infraction Code ', '', $point->title)) : 'Infraction',
                'description' => (string) $point->description,
                'points' => (float) $point->points,
            ] : null,
        ];
    }

    /**
     * One colour per day, checked in this order:
     *  red   - resigned, or a late infraction (D, E, F: "05:01-D", "CI-E", "05:02-F/EO");
     *  blue  - leave, and early outs on any status (EO, P/EO, EEO, P/EEO, EEO-IO, SL/EEO-IO...);
     *  gray  - absent or suspended (CI, CI-A, AWOL, OC, SUS...);
     *  green - work, required duty, holidays (P, 12:11-NC...); gray for anything else.
     * Also used by the Attendance Summary so both reports colour a day the same way.
     */
    public static function tone(string $status, string $remarks): string
    {
        $statusParts = explode('/', strtolower($status));
        $has = fn (string $s) => in_array($s, $statusParts, true);
        $parts = explode('/', strtoupper($remarks));

        $late = preg_match('/(^|-)(D|E|F|NC|GS|PO|TI|IO|TP|PcP)$/', $parts[0]) === 1;
        $earlyOut = collect($parts)->contains(fn ($p) => $p === 'EO' || str_starts_with($p, 'EEO'));

        return match (true) {
            $has('res') || $parts[0] === 'RES' => 'alert',
            $late => 'alert',
            ($has('leave') && ! $has('work')) || $earlyOut => 'leave',
            $has('ab') || $has('sus') => 'muted',
            $has('work') || $has('rd') || $has('rh') || $has('snwh') || $has('ot') || $parts[0] === 'RD' => 'work',
            default => 'muted',
        };
    }

    /**
     * Late when the login minute is after the shift's start minute (seconds
     * are ignored, so 08:00:59 is on time and 08:01 is late). Times are
     * compared around the clock, so for overnight shifts an evening login
     * before a 12 AM / 1 AM start is early and a 12:30 AM login for an
     * 11 PM shift is late.
     */
    private function isLate(?string $startshift, string $loginam): bool
    {
        $start = $this->minutes($startshift);
        $login = $this->minutes($loginam);
        if ($start === null || $login === null) {
            return false;
        }

        // Difference in minutes, folded into -12h .. +12h.
        $diff = (($login - $start) % 1440 + 1440) % 1440;
        if ($diff > 720) {
            $diff -= 1440;
        }

        return $diff > 0;
    }

    /** Minutes since midnight of a time, without seconds. */
    private function minutes(?string $time): ?int
    {
        $ts = $time !== null && $time !== '' && $time !== '0' ? strtotime($time) : false;

        return $ts === false ? null : (int) date('G', $ts) * 60 + (int) date('i', $ts);
    }

    /**
     * attendance.shiftchange, e.g. "05:00:00-14:00:00" or "23:00-08:00".
     *
     * @return array{start: string, end: string}|null
     */
    private function parseShift(?string $value): ?array
    {
        if ($value === null || ! preg_match('/^\s*(\d{1,2}:\d{2}(?::\d{2})?)\s*-\s*(\d{1,2}:\d{2}(?::\d{2})?)\s*$/', $value, $m)) {
            return null;
        }

        return strtotime($m[1]) === false ? null : ['start' => $m[1], 'end' => $m[2]];
    }

    private function clock(?string $time, string $format): string
    {
        $ts = $time ? strtotime($time) : false;

        return $ts === false ? '—' : date($format, $ts);
    }
}
