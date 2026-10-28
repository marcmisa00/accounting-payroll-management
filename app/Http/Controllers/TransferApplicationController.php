<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesHrisApplications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Site transfer requests from hris.work_transfer, grouped company → department
 * and filtered by transfer date. Accounting can acknowledge an application;
 * nothing else is written.
 */
class TransferApplicationController extends Controller
{
    use HandlesHrisApplications;

    private const TABLE = 'work_transfer';

    public function index(Request $request): View
    {
        $range = $this->dateRange($request, self::TABLE, 'date_transfer');
        $companies = $this->companies();

        $rows = $range['rangeFrom'] > $range['rangeTo'] ? collect() : $this->hris()->table('work_transfer as a')
            ->join('employee_details as ed', 'ed.idno', '=', 'a.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'a.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            ->whereBetween('a.date_transfer', [$range['rangeFrom'], $range['rangeTo']])
            ->select(
                'a.id', 'a.idno', 'a.date_transfer', 'a.end_date', 'a.prev_loc', 'a.new_loc', 'a.reason',
                'a.datetime', 'a.application_status', 'a.acknowledged',
                'a.it_acknowledged_by', 'a.hr_acknowledged_by', 'a.acc_acknowledged_by',
                'ep.lastname', 'ep.firstname',
                'ed.company', 'ed.department as department_id', 'd.department as department_name'
            )
            ->orderByDesc('a.date_transfer')
            ->orderByDesc('a.id')
            ->get();

        $tabs = $this->tabs($companies, $rows, fn ($r) => [
            'id'     => (string) $r->idno,
            'name'   => trim($r->lastname . ', ' . $r->firstname, ', ') ?: (string) $r->idno,
            'start'  => $this->date($r->date_transfer),
            'end'    => $this->date($r->end_date),
            'from'   => trim((string) $r->prev_loc) ?: null,
            'to'     => trim((string) $r->new_loc) ?: null,
            'reason' => $r->reason,
        ] + $this->applicationState($r));

        return view('applications.transfer', $range + [
            'tabs'  => $tabs,
            'total' => $rows->count(),
        ]);
    }

    public function acknowledgeApplication(int $id): JsonResponse
    {
        return $this->acknowledge(self::TABLE, $id);
    }
}
