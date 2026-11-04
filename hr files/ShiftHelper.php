<?php

namespace App\Helpers;

class ShiftHelper
{
    /**
     * Determine if the given shift is a Graveyard (GY) shift.
     * GY is defined as starting between 8:00 PM (20:00:00) and 12:00 AM (00:00:00).
     */
    public static function isGyShift($startshift): bool
    {
        if (empty($startshift)) {
            return false;
        }

        // Convert to 24-hour format (H:i:s) for accurate comparison
        $time = date('H:i:s', strtotime($startshift));

        // Check if the time is between 20:00:00 (8 PM) and 23:59:59 (11:59 PM),
        // OR exactly 00:00:00 (12 AM)
        return ($time >= '20:00:00' && $time <= '23:59:59') || $time === '00:00:00';
    }

    /**
     * SQL counterpart of isGyShift() for a TIME column, so query filters
     * and row colouring use the same 8 PM - 12 AM window.
     */
    public static function gyShiftSql(string $column): string
    {
        return "({$column} >= '20:00:00' OR {$column} = '00:00:00')";
    }

    /**
     * employee_details.dls for a shift start time: 1 when the shift starts
     * in the 11 PM or 12 AM hour. Mirrors cron/automatic_movements.php on
     * the main site, which applies scheduled shift movements, so immediate
     * and scheduled moves store the same value.
     */
    public static function dlsFor($startshift): int
    {
        if (empty($startshift)) {
            return 0;
        }

        $hour = (int) date('H', strtotime($startshift));

        return ($hour === 23 || $hour === 0) ? 1 : 0;
    }

    /**
     * Shift type the same cron assigns: night for starts from 11 PM to 4 AM.
     */
    public static function shiftTypeFor($startshift): string
    {
        if (empty($startshift)) {
            return 'morning';
        }

        $hour = (int) date('H', strtotime($startshift));

        return ($hour >= 23 || $hour <= 4) ? 'night' : 'morning';
    }

    /**
     * Optional: Determine if it's a Morning (AM) shift.
     */
    public static function isAmShift($startshift): bool
    {
        return ! self::isGyShift($startshift);
    }
}
