<?php

namespace App\Livewire\Attendance;

use App\Livewire\Attendance\Concerns\SummaryFilters;
use App\Services\AttendanceSummaryReport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Attendance Summary (absences / late): one column per day and incident /
 * leave totals per employee. Read only.
 */
class AttendanceSummary extends Component
{
    use SummaryFilters;

    /** Longest range loaded at once (same as Employee Logs). */
    public const MAX_DAYS = EmployeeLogs::MAX_DAYS;

    protected DatabaseManager $db;

    protected AttendanceSummaryReport $report;

    public function boot(DatabaseManager $db, AttendanceSummaryReport $report): void
    {
        $this->db = $db;
        $this->report = $report;
    }

    /** Employees in scope, before search / fixed filtering. */
    #[Computed]
    public function employees(): Collection
    {
        return $this->report->employees($this->company, $this->departments, $this->startDate, $this->endDate);
    }

    private function conn(): ConnectionInterface
    {
        return $this->db->connection('hris');
    }

    public function render()
    {
        $employees = AttendanceSummaryReport::filter($this->employees, $this->search, $this->onlyFixed, $this->onlyGy, $this->onlyAm);
        $dates = AttendanceSummaryReport::dates($this->startDate, $this->endDate);

        // Everyone on one page, as on the old report.
        $employees->each(fn ($emp) => $emp->cells = $this->report->cells($emp, $dates));

        $months = collect($dates)->pluck('month')->unique()->values();

        return view('livewire.attendance.attendance-summary', [
            'employees' => $employees,
            'dates' => $dates,
            'monthRange' => $months->count() > 1 ? $months->join(' → ') : null,
            'companyName' => $this->company !== ''
                ? ($this->conn()->table('settings')->where('companycode', $this->company)->value('companyname') ?: $this->company)
                : 'Selected Departments',
            'exportUrl' => route('attendance.summary.export', $this->exportQuery()),
        ])->layout('layouts.report');
    }
}
