<?php

namespace App\Helpers;

/**
 * Graveyard shift rule of the HR portal's ShiftHelper, used by the attendance
 * summaries so both portals flag the same employees. Keep in sync with HR.
 */
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
}
