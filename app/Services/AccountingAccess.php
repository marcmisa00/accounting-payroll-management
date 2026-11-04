<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Who may use Accounting. Access is managed in HRIS, so this is checked at
 * registration, at login, and again during the session (AccountingAuth).
 */
class AccountingAccess
{
    /**
     * ACCOUNTING is in hris.users.access (a comma-separated list, e.g.
     * "HR,ACCOUNTING,EMPLOYEE PORTAL") and the employee is not resigned.
     */
    public static function allows(string $idno): bool
    {
        $conn = DB::connection('hris');

        $access = $conn->table('users')->where('idno', $idno)->value('access');

        if (!$access) {
            return false;
        }

        $accessList = array_map(fn ($item) => strtoupper(trim($item)), explode(',', $access));

        if (!in_array('ACCOUNTING', $accessList, true)) {
            return false;
        }

        return !$conn->table('employee_details')
            ->where('idno', $idno)
            ->where('status', 'like', '%RESIGNED%')
            ->exists();
    }
}
