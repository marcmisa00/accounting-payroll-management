<?php

namespace App\Services;

/**
 * The day colours of the HR portal's EmployeeLogsReport, which the Attendance
 * Summary uses so both portals colour a day the same way. Only TONES and
 * tone() are kept: Accounting has no Employee Logs page. Keep in sync with HR.
 */
class EmployeeLogsReport
{
    /** Remarks colour by the day's status, shown in the legend in this order. */
    public const TONES = [
        'work' => 'Work / RD',
        'leave' => 'Leave / early out',
        'alert' => 'Late / resigned',
        'muted' => 'Absent / suspended',
    ];

    /**
     * One colour per day, checked in this order:
     *  red   - resigned, or a late infraction (D, E, F: "05:01-D", "CI-E", "05:02-F/EO");
     *  blue  - leave, and early outs on any status (EO, P/EO, EEO, P/EEO, EEO-IO, SL/EEO-IO...);
     *  gray  - absent or suspended (CI, CI-A, AWOL, OC, SUS...);
     *  green - work, required duty, holidays (P, 12:11-NC...); gray for anything else.
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
}
