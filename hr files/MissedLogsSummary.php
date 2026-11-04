<?php

namespace App\Livewire\Attendance;

use App\Livewire\Attendance\Concerns\SummaryFilters;
use App\Services\AttendanceSummaryReport;
use App\Services\MissedLogsSummaryReport;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Missed Logs / OB Summary: the Attendance Summary's day grid for lunch
 * over-breaks and missed logins (M, L, B-, I-, L-), with totals and points
 * per employee. Read only.
 */
class MissedLogsSummary extends Component
{
    use SummaryFilters;

    /** Show only employees with at least one infraction in the range. */
    public bool $onlyIncidents = false;

    protected DatabaseManager $db;

    protected MissedLogsSummaryReport $report;

    public function boot(DatabaseManager $db, MissedLogsSummaryReport $report): void
    {
        $this->db = $db;
        $this->report = $report;
    }

    /** Employees in scope, before search / shift filtering. */
    #[Computed]
    public function employees(): Collection
    {
        return $this->report->employees($this->company, $this->departments, $this->startDate, $this->endDate);
    }

    public function render()
    {
        $all = AttendanceSummaryReport::filter($this->employees, $this->search, $this->onlyFixed, $this->onlyGy, $this->onlyAm);
        $employees = $this->onlyIncidents ? $all->filter(fn ($e) => $e->totals['total'] > 0)->values() : $all;
        $dates = AttendanceSummaryReport::dates($this->startDate, $this->endDate);

        $employees->each(fn ($emp) => $emp->cells = $this->report->cells($emp, $dates));

        $months = collect($dates)->pluck('month')->unique()->values();
        $codes = array_values(MissedLogsSummaryReport::CODES);

        return view('livewire.attendance.missed-logs-summary', [
            'employees' => $employees,
            'dates' => $dates,
            'codes' => $codes,
            // Totals of the rows shown, for the stat strip.
            'stats' => [
                'employees' => $employees->count(),
                'withIncidents' => $employees->filter(fn ($e) => $e->totals['total'] > 0)->count(),
                'codes' => collect($codes)->mapWithKeys(fn ($c) => [$c => $employees->sum(fn ($e) => $e->totals[$c])])->all(),
                'points' => $employees->sum(fn ($e) => $e->totals['points']),
            ],
            'monthRange' => $months->count() > 1 ? $months->join(' → ') : null,
            'companyName' => $this->company !== ''
                ? ($this->db->connection('hris')->table('settings')->where('companycode', $this->company)->value('companyname') ?: $this->company)
                : 'Selected Departments',
            'exportUrl' => route('attendance.missed-summary.export', $this->exportQuery(['incidents' => $this->onlyIncidents ? 1 : null])),
        ])->layout('layouts.report');
    }
}
