<?php

namespace App\Http\Controllers\Concerns;

/**
 * HRIS stores leave and overtime approval status as one string, e.g.
 * "Approved - Batara (Team Manager) [Nov 27, 2025 - 9:57 AM]".
 */
trait ParsesHrisStatus
{
    /**
     * "Approved - Batara (Team Manager) [Nov 27, 2025 - 9:57 AM]"
     *   → ['approved', 'Batara (Team Manager) · Nov 27, 2025 - 9:57 AM', false]
     * A leading "*" (as HRIS stores some statuses) is kept as a flag, not dropped.
     */
    private function parseStatus(?string $status): array
    {
        $status  = trim((string) $status);
        $flagged = str_starts_with($status, '*');
        $status  = ltrim($status, '* ');

        $kind = match (true) {
            str_starts_with($status, 'Approved')    => 'approved',
            str_starts_with($status, 'Disapproved') => 'disapproved',
            str_starts_with($status, 'Cancelled')   => 'cancelled',
            str_starts_with($status, 'Pending')     => 'pending',
            default                                 => 'other',
        };

        $detail = null;
        if (preg_match('/^\w+\s*-\s*(.*?)\s*(?:\[(.+)\])?$/u', $status, $m)) {
            $who  = trim(preg_replace('/\(\s*\)/', '', $m[1]));
            $who  = preg_replace('/\s+\)/', ')', $who);
            $when = trim($m[2] ?? '');
            $detail = trim($who . ($who && $when ? ' · ' : '') . $when) ?: null;
        }

        if ($kind === 'other') {
            $detail = $status ?: null;
        }

        return [$kind, $detail, $flagged];
    }
}
