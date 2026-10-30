@extends('layouts.app')

@section('title', $company . ' - ' . $bankLabel . ' - Accounting Portal')
@section('page-title', $company . ' · ' . $bankLabel)

@section('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/benefits.css', 'resources/css/dashboard-d.css'])
@endsection

@section('content')
@php
    $coKey = 'co-' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $company));
    $from = ['from' => 'dashboard', 'bank' => $bank];
@endphp

<div class="dash bf dd">

    {{-- ===================== Header ===================== --}}
    <div class="section-head">
        <div>
            <div class="bf-crumbs">
                <a href="{{ route('dashboard', ['period' => $period->id]) }}" class="bf-icon-btn" aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
                <a href="{{ route('dashboard', ['period' => $period->id]) }}">Dashboard</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $company }} · {{ $bankLabel }}</span>
            </div>
            <h2>{{ $company }} · {{ $bankLabel }}</h2>
            <div class="sub">
                Payroll period {{ date('M d, Y', strtotime($period->periodfrom)) }} – {{ date('M d, Y', strtotime($period->periodto)) }}
            </div>
        </div>
    </div>

    {{-- ===================== Stats ===================== --}}
    <div class="stats bf-stats dd-stats">
        <div class="stat reveal">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-label">Employees</div>
            <div class="stat-value">{{ number_format($employees->count()) }}</div>
            <div class="stat-foot">Active, excluding resigned</div>
        </div>
        <div class="stat reveal">
            <div class="stat-icon"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="stat-label">Total net pay</div>
            <div class="stat-value">{{ number_format($total, 2) }}</div>
            <div class="stat-foot">{{ $noBank ? 'No bank set' : 'Paid through ' . $bankLabel }}</div>
        </div>
    </div>

    @if ($noBank)
        <div class="bf-alert dd-notice" role="note">
            <i class="fa-solid fa-triangle-exclamation"></i>
            These employees have no bank set in their payroll record, so they can't be paid by bank transfer until one is added.
        </div>
    @endif

    @if ($employees->isNotEmpty())
        <div class="bf-toolbar">
            <div class="bf-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="ddSearch" placeholder="Search by employee ID, name or team…"
                       aria-label="Search by employee ID, name or team" autocomplete="off">
            </div>
            <span class="bf-total" id="ddCount" aria-live="polite"></span>
        </div>
    @endif

    {{-- ===================== Table ===================== --}}
    <div class="company bf-card {{ $coKey }}">
        @if ($employees->isEmpty())
            <div class="bf-empty-row">
                {{ $noBank ? 'Every employee in this company has a bank set.' : 'No employees found for this bank.' }}
            </div>
        @else
            <div class="bf-table-wrap">
                <table class="bank-table bf-table dd-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Team</th>
                            <th>Account number</th>
                            <th class="num">Net pay</th>
                            <th class="dd-action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="ddRows">
                        @foreach ($employees as $e)
                            @php
                                $suffix = !empty($e->suffix) && $e->suffix !== 'N/A' ? ' ' . $e->suffix : '';
                                $name = $e->lastname . ', ' . $e->firstname . $suffix;
                                $editUrl = route('payroll.edit.show', [
                                    'payroll' => $period->id,
                                    'idno'    => $e->idno,
                                    'company' => $company,
                                    'dept'    => $e->dept_id,
                                ] + $from);
                            @endphp
                            <tr data-search="{{ strtolower($e->idno . ' ' . $name . ' ' . $e->department) }}">
                                <td class="bf-id">{{ $e->idno }}</td>
                                <td class="strong">{{ $name }}</td>
                                <td>{{ $e->department ?: '—' }}</td>
                                <td>
                                    @if ($e->account_number !== '')
                                        {{ $e->account_number }}
                                    @else
                                        <span class="bf-muted">—</span>
                                    @endif
                                </td>
                                <td class="num strong">{{ number_format($e->net_pay, 2) }}</td>
                                <td class="dd-action">
                                    <a href="{{ $editUrl }}" class="bf-btn bf-btn-sm" title="View payroll of {{ $name }}">
                                        <i class="fa-solid fa-file-invoice-dollar"></i> View payroll
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        <tr id="ddNoMatch" hidden>
                            <td colspan="6" class="bf-empty-row">No employees match your search.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">Total net pay</td>
                            <td class="num">{{ number_format($total, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

</div>

@if ($employees->isNotEmpty())
<script>
    // Filters the rows already on the page (display only)
    (function () {
        const input = document.getElementById('ddSearch');
        const rows = Array.from(document.querySelectorAll('#ddRows tr[data-search]'));
        const noMatch = document.getElementById('ddNoMatch');
        const count = document.getElementById('ddCount');

        function apply() {
            const term = input.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach(function (row) {
                const match = term === '' || row.dataset.search.includes(term);
                row.hidden = !match;
                if (match) shown++;
            });
            noMatch.hidden = shown !== 0;
            count.innerHTML = term === ''
                ? '<strong>' + rows.length + '</strong> employees'
                : '<strong>' + shown + '</strong> of ' + rows.length + ' employees';
        }

        input.addEventListener('input', apply);
        apply();
    })();
</script>
@endif
@endsection
