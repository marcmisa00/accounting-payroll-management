@extends('layouts.app')

@section('title', 'Payroll History - Accounting Portal')
@section('page-title', 'Payroll History')

@section('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/benefits.css'])
@endsection

@section('content')
<div class="dash bf">

    <div class="section-head">
        <div>
            <h2>Payroll History</h2>
            <div class="sub">
                @if ($searchAllPeriods)
                    Add-on, deduction and Edit Time changes matching “{{ $employee }}” across all payroll periods, newest first.
                @else
                    Add-on, deduction and Edit Time changes for Payroll #{{ $payroll->id }}
                    ({{ date('M d, Y', strtotime($payroll->periodfrom)) }} – {{ date('M d, Y', strtotime($payroll->periodto)) }}), newest first.
                @endif
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('payroll.history', ['payroll' => $payroll->id]) }}" class="bf-toolbar bf-filters">
        <div class="bf-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="employee" value="{{ $employee }}" placeholder="Employee ID or name…" aria-label="Employee ID or name" autocomplete="off">
        </div>
        <div class="select-wrap">
            <select name="period" class="pick" aria-label="Payroll period">
                @foreach ($payrolls as $period)
                    <option value="{{ $period->id }}" @selected((string) $period->id === (string) $payroll->id)>
                        Payroll #{{ $period->id }} — {{ date('M d, Y', strtotime($period->periodfrom)) }} – {{ date('M d, Y', strtotime($period->periodto)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="select-wrap">
            <select name="record_type" class="pick" aria-label="History type">
                <option value="">All types</option>
                <option value="payroll-ADD" @selected($recordType === 'payroll-ADD')>Payroll-ADD — Add-on</option>
                <option value="payroll-DED" @selected($recordType === 'payroll-DED')>Payroll-DED — Deduction</option>
                <option value="EDIT TIME" @selected($recordType === 'EDIT TIME')>EDIT TIME</option>
            </select>
        </div>
        <div class="select-wrap">
            <select name="user" class="pick" aria-label="Changed by">
                <option value="">Anyone</option>
                @foreach ($users as $u)
                    <option value="{{ $u->idno }}" @selected(($filters['user'] ?? '') === $u->idno)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <label class="bf-inline">From <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="bf-inline">To <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button type="submit" class="bf-btn bf-btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
        @if ($filters)
            <a href="{{ route('payroll.history', ['payroll' => $payroll->id]) }}" class="bf-btn bf-btn-ghost"><i class="fa-solid fa-xmark"></i> Reset</a>
        @endif
        <span class="bf-total"><strong>{{ number_format($entries->total()) }}</strong> {{ Str::plural('change', $entries->total()) }}</span>
    </form>

    @forelse ($entries as $entry)
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

            $employeeName = trim(($entry->lastname ?? '') . ', ' . ($entry->firstname ?? ''), ', ');

            $changedByName = trim(($entry->changed_by_firstname ?? '') . ' ' . ($entry->changed_by_lastname ?? ''))
                ?: ($entry->changed_by ?: '—');
        @endphp
        <article class="bf-entry">
            <header class="bf-entry-head">
                <div>
                    <a class="bf-entry-emp" href="{{ route('payroll.history', ['payroll' => $payroll->id, 'employee' => $entry->idno]) }}">
                        {{ $employeeName ?: 'Unknown employee' }} <span class="bf-id">{{ $entry->idno }}</span>
                    </a>
                    <div class="bf-entry-meta">
                        <i class="fa-regular fa-user"></i> {{ $changedByName }}
                        <span aria-hidden="true">·</span> <i class="fa-regular fa-clock"></i>
                        {{ $entry->changed_at ? date('M d, Y h:i A', strtotime($entry->changed_at)) : '—' }}
                        <span aria-hidden="true">·</span> <span class="badge">{{ $typeLabel }}</span>
                        @if ($categoryLabel !== '')
                            {{ $categoryLabel }}
                        @endif
                        @if ($searchAllPeriods && $entry->payrollperiod)
                            <span aria-hidden="true">·</span> Payroll #{{ $entry->payrollperiod }}
                        @endif
                    </div>
                </div>
            </header>

            <ul class="bf-changes">
                <li>
                    <span class="bf-change-field">{{ strtoupper($entry->action ?? '-') }} — {{ $entry->description ?? '-' }}</span>
                    <span class="bf-change-old">{{ $entry->old_value !== null ? number_format((float) $entry->old_value, 2) : '—' }}</span>
                    <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
                    <span class="bf-change-new">{{ $entry->new_value !== null ? number_format((float) $entry->new_value, 2) : '—' }}</span>
                </li>
            </ul>
        </article>
    @empty
        <div class="empty"><i class="fa-regular fa-folder-open"></i><div>No changes match your filters.</div></div>
    @endforelse

    <div class="bf-foot">
        <span></span>
        {{ $entries->onEachSide(1)->links('benefits.partials.pagination') }}
    </div>
</div>
@endsection
