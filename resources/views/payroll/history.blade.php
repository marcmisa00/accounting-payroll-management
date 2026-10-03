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

<a href="{{ $backUrl }}">
    <i class="fa-solid fa-arrow-left"></i>
    BACK
</a>
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
            Payroll #{{ $payroll->id }}
        </h4>
        <p>
            {{ date('M d, Y', strtotime($payroll->periodfrom)) }}
            -
            {{ date('M d, Y', strtotime($payroll->periodto)) }}
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
            {{-- TYPE --}}
            <div class="history-filter-group">
                <label for="type">
                    Type
                </label>
                <select name="type" id="type">
                    <option value="">
                        All Types
                    </option>
                    <option value="addon"
                        {{ ($type ?? '') === 'addon' ? 'selected' : '' }}>
                        Add-on
                    </option>
                    <option value="deduction"
                        {{ ($type ?? '') === 'deduction' ? 'selected' : '' }}>
                        Deduction
                    </option>
                    <option value="payroll"
                        {{ ($type ?? '') === 'payroll' ? 'selected' : '' }}>
                        Edit Time
                    </option>
                </select>
            </div>

            {{-- ACTION --}}
            <div class="history-filter-group">
                <label for="action">
                    Action
                </label>
                <select name="action" id="action">
                    <option value="">
                        All Actions
                    </option>
                    <option value="INSERT"
                        {{ ($action ?? '') === 'INSERT' ? 'selected' : '' }}>
                        INSERT
                    </option>
                    <option value="UPDATE"
                        {{ ($action ?? '') === 'UPDATE' ? 'selected' : '' }}>
                        UPDATE
                    </option>
                    <option value="DELETE"
                        {{ ($action ?? '') === 'DELETE' ? 'selected' : '' }}>
                        DELETE
                    </option>
                </select>
            </div>
            {{-- SEARCH --}}
            <div class="history-filter-actions">
                <button type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search
                </button>
                <a href="{{ route('payroll.history', ['payroll' => $payroll->id]) }}">
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
                            <th>Date / Time</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                            <th>Changed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            @php
                                $typeLabel = match ($entry->type) {
                                    'addon'     => 'Add-on',
                                    'deduction' => 'Deduction',
                                    'payroll'   => 'Edit Time',
                                    default     => ucfirst($entry->type ?? '-'),
                                };

                                $categoryLabel = match ($entry->category) {
                                    'payroll'             => 'Payroll',
                                    'constant'            => 'Constant',
                                    'attendance'          => 'Attendance',
                                    'attendance_override' => 'Attendance Override',
                                    default               => ucfirst($entry->category ?? '-'),
                                };
                                $actionClass = match (strtoupper($entry->action ?? '')) {
                                    'INSERT' => 'history-badge-insert',
                                    'UPDATE' => 'history-badge-update',
                                    'DELETE' => 'history-badge-delete',
                                    default  => 'history-badge-default',
                                };
                            @endphp
                            <tr>
                                {{-- DATE / TIME --}}
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
                                {{-- EMPLOYEE --}}
                                <td class="history-employee">
                                    <strong>
                                        {{ trim(
                                            ($entry->firstname ?? '') . ' ' .
                                            ($entry->lastname ?? '')
                                        ) ?: ($entry->idno ?? '-') }}
                                    </strong>
                                </td>
                                {{-- TYPE --}}
                                <td>
                                    {{ $typeLabel }}
                                </td>
                                {{-- CATEGORY --}}
                                <td>
                                    {{ $categoryLabel }}
                                </td>
                                {{-- ACTION --}}
                                <td>
                                    <span class="history-badge {{ $actionClass }}">
                                        {{ strtoupper($entry->action ?? '-') }}
                                    </span>
                                </td>
                                {{-- DESCRIPTION --}}
                                <td class="history-description">
                                    {{ $entry->description ?? '-' }}
                                </td>
                                {{-- OLD VALUE --}}
                                <td class="history-value">
                                    @if ($entry->old_value !== null)
                                        {{ number_format((float) $entry->old_value, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                {{-- NEW VALUE --}}
                                <td class="history-value">
                                    @if ($entry->new_value !== null)
                                        {{ number_format((float) $entry->new_value, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                {{-- CHANGED BY --}}
                                <td class="history-employee">
                                    <strong>
                                        {{ trim(
                                            ($entry->changed_by_firstname ?? '') . ' ' .
                                            ($entry->changed_by_lastname ?? '')
                                        ) ?: ($entry->changed_by ?? '-') }}
                                    </strong>
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
