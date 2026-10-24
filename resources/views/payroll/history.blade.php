@extends('layouts.app')

@section('content')

@vite(['resources/css/payroll-history.css'])

@php
$backUrl = route('payroll.history.index', [
'company' => $company,
'dept'    => $deptId,
]);
@endphp

<div class="page-header-row">

<span class="title">
    <i class="fa-solid fa-clock-rotate-left"></i>
    PAYROLL HISTORY
</span>

</div>
<div class="centered-container">

<div class="content-panel">
    {{-- PAYROLL INFORMATION --}}
    <div class="panel-heading">
        <h4>
            @if ($searchAllPeriods ?? false)
                All Payroll Periods
            @else
                Payroll #{{ $payroll->id }}
            @endif
        </h4>

        <p>
            @if ($searchAllPeriods ?? false)
                Showing matching employee history across all payroll periods.
            @else
                {{ date('M d, Y', strtotime($payroll->periodfrom)) }}
                -
                {{ date('M d, Y', strtotime($payroll->periodto)) }}
            @endif
        </p>
    </div>
    <div class="panel-body">
        {{-- FILTERS --}}
        <form method="GET"
              action="{{ route('payroll.history', ['payroll' => $payroll->id]) }}"
              class="history-filters">
            {{-- EMPLOYEE --}}
           <div class="history-filter-group">
            <label for="employee">
                Employee
            </label>
            <input
                type="text"
                name="employee"
                id="employee"
                value="{{ $employee ?? '' }}"
                placeholder="Name or employee ID..."
                autocomplete="off"
            >
        </div>
        <div class="history-filter-group">
            <label for="period">Payroll Period</label>

            <select name="period" id="period">
                @foreach ($payrolls as $period)
                    <option
                        value="{{ $period->id }}"
                        {{ (string) $period->id === (string) $payroll->id ? 'selected' : '' }}
                    >
                        Payroll #{{ $period->id }}
                        —
                        {{ date('M d, Y', strtotime($period->periodfrom)) }}
                        -
                        {{ date('M d, Y', strtotime($period->periodto)) }}
                    </option>
                @endforeach
            </select>
        </div>
          
<div class="history-filter-group">
    <label for="record_type">History Type</label>

    <select name="record_type" id="record_type">
        <option value=""
            {{ ($recordType ?? '') === '' ? 'selected' : '' }}>
            All Types
        </option>

        <option value="payroll-ADD"
            {{ ($recordType ?? '') === 'payroll-ADD' ? 'selected' : '' }}>
            payroll-ADD — Add-on
        </option>

        <option value="payroll-DED"
            {{ ($recordType ?? '') === 'payroll-DED' ? 'selected' : '' }}>
            payroll-DED — Deduction
        </option>

        <option value="EDIT TIME"
            {{ ($recordType ?? '') === 'EDIT TIME' ? 'selected' : '' }}>
            EDIT TIME
        </option>
    </select>
</div>

            {{-- SEARCH --}}
            <div class="history-filter-actions">
                <button type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search
                </button>
             <a href="{{ route('payroll.history.index', [
                    'company' => $company,
                    'dept' => $deptId,
                ]) }}">
                    Clear
                </a>
            </div>
        </form>
        {{-- NO FILTER SELECTED --}}
        @if (!$hasFilter)
            <div class="history-empty">
                <i class="fa-solid fa-filter"></i>
                <p>
                    Select an employee, type, or action
                    to view payroll history.
                </p>
            </div>
        {{-- FILTERED BUT NO RESULTS --}}
        @elseif ($entries->isEmpty())
            <div class="history-empty">
                <i class="fa-solid fa-circle-info"></i>
                <p>
                    No history found for the selected filters.
                </p>
            </div>
        {{-- RESULTS --}}
        @else
            <div class="history-summary">
                <span>
                    <strong>{{ $entries->count() }}</strong>
                    history entries
                </span>
            </div>
            <div class="history-table-wrapper">
                <table class="history-table">
                    
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Action - Description</th>
                            <th>Value (Old → New)</th>
                            <th>Changed By</th>
                        </tr>
                    </thead>

                                    
                    <tbody>
                        @foreach ($entries as $entry)
                            @php
                                $typeLabel = match ($entry->type) {
                                    'addon'     => 'Payroll-ADD',
                                    'deduction' => 'Payroll-DED',
                                    'payroll'   => 'EDIT TIME',
                                    default     => strtoupper($entry->type ?? '-'),
                                };

                                $categoryLabel = match ($entry->category) {
                                    'payroll'             => 'Payroll',
                                    'constant'            => 'Constant',
                                    'attendance'          => 'Attendance',
                                    'attendance_override' => 'Attendance Override',
                                    default               => ucfirst($entry->category ?? ''),
                                };

                                $actionClass = match (strtoupper($entry->action ?? '')) {
                                    'INSERT' => 'history-badge-insert',
                                    'UPDATE' => 'history-badge-update',
                                    'DELETE' => 'history-badge-delete',
                                    default  => 'history-badge-default',
                                };

                                $employeeName = trim(
                                    ($entry->firstname ?? '') . ' ' .
                                    ($entry->lastname ?? '')
                                ) ?: ($entry->idno ?? '-');

                                $changedByName = trim(
                                    ($entry->changed_by_firstname ?? '') . ' ' .
                                    ($entry->changed_by_lastname ?? '')
                                ) ?: ($entry->changed_by ?? '-');
                            @endphp

                            <tr>
                                {{-- DATE --}}
                                <td class="history-date">
                                    {{ $entry->changed_at
                                        ? date('M d, Y', strtotime($entry->changed_at))
                                        : '-' }}

                                    <small>
                                        {{ $entry->changed_at
                                            ? date('h:i A', strtotime($entry->changed_at))
                                            : '' }}
                                    </small>
                                </td>

                                {{-- NAME --}}
                                <td class="history-employee">
                                    <strong>{{ $employeeName }}</strong>

                                    @if (!empty($entry->idno))
                                        <small>{{ $entry->idno }}</small>
                                    @endif
                                </td>

                                {{-- TYPE + CATEGORY --}}
                                <td>
                                    <strong>{{ $typeLabel }}</strong>

                                    @if ($categoryLabel !== '')
                                        <small class="history-category">
                                            {{ $categoryLabel }}
                                        </small>
                                    @endif
                                </td>

                                {{-- ACTION + DESCRIPTION --}}
                                <td class="history-description">
                                    <span class="history-badge {{ $actionClass }}">
                                        {{ strtoupper($entry->action ?? '-') }}
                                    </span>

                                    <div>
                                        {{ $entry->description ?? '-' }}
                                    </div>
                                </td>

                                {{-- OLD VALUE → NEW VALUE --}}
                                <td class="history-value">
                                    <span>
                                        @if ($entry->old_value !== null)
                                            {{ number_format((float) $entry->old_value, 2) }}
                                        @else
                                            -
                                        @endif
                                    </span>

                                    <span aria-hidden="true"> → </span>

                                    <strong>
                                        @if ($entry->new_value !== null)
                                            {{ number_format((float) $entry->new_value, 2) }}
                                        @else
                                            -
                                        @endif
                                    </strong>
                                </td>

                                {{-- CHANGED BY --}}
                                <td class="history-employee">
                                    <strong>{{ $changedByName }}</strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        @endif
    </div>
</div>
</div>
@endsection
