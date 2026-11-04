<?php

namespace App\Livewire\Attendance\Concerns;

use App\Livewire\Attendance\EmployeeLogs;
use Carbon\Carbon;
use Livewire\Attributes\Locked;

/**
 * Scope and filters shared by the day-grid summaries (Attendance Summary,
 * Missed Logs / OB Summary): company and departments from the Attendance
 * page's link, a date range applied with a button and capped at MAX_DAYS,
 * search and the shift checkboxes.
 */
trait SummaryFilters
{
    #[Locked]
    public string $company = '';

    #[Locked]
    public array $departments = [];

    /** Range shown. Changed only through applyRange(). */
    #[Locked]
    public string $startDate = '';

    #[Locked]
    public string $endDate = '';

    /** Range inputs, applied with the Apply button. */
    public string $fromDate = '';

    public string $toDate = '';

    #[Locked]
    public bool $rangeCapped = false;

    public string $search = '';

    public bool $onlyFixed = false;

    public bool $onlyGy = false;

    public bool $onlyAm = false;

    public function mount(): void
    {
        $company = request()->query('company', '');
        $this->company = is_string($company) ? $company : '';

        $depts = request()->query('departments', []);
        if (is_array($depts)) {
            $this->departments = array_values(array_unique(array_filter(array_map('intval', $depts))));
        }

        $this->setRange(
            $this->dateParam('startdate', now()->startOfMonth()->toDateString()),
            $this->dateParam('enddate', now()->endOfMonth()->toDateString()),
        );
    }

    public function applyRange(): void
    {
        $this->validate([
            'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate'],
        ], [
            'toDate.after_or_equal' => 'End date must be on or after the start date.',
        ]);

        $this->setRange($this->fromDate, $this->toDate);
    }

    /** Query string for the page's export link. */
    protected function exportQuery(array $extra = []): array
    {
        return array_filter([
            'company' => $this->company,
            'departments' => $this->departments,
            'startdate' => $this->startDate,
            'enddate' => $this->endDate,
            'search' => trim($this->search) !== '' ? trim($this->search) : null,
            'fixed' => $this->onlyFixed ? 1 : null,
            'gy' => $this->onlyGy ? 1 : null,
            'am' => $this->onlyAm ? 1 : null,
            ...$extra,
        ], fn ($v) => $v !== '' && $v !== null && $v !== []);
    }

    private function setRange(string $start, string $end): void
    {
        if ($end < $start) {
            $end = $start;
        }
        $maxEnd = Carbon::parse($start)->addDays(EmployeeLogs::MAX_DAYS - 1)->toDateString();
        $this->rangeCapped = $end > $maxEnd;

        $this->startDate = $this->fromDate = $start;
        $this->endDate = $this->toDate = min($end, $maxEnd);
    }

    private function dateParam(string $key, string $default): string
    {
        $value = request()->query($key);

        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? $value : $default;
    }
}
