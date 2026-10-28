@extends('layouts.app')

@section('title', 'Salary & Benefits - Accounting Portal')
@section('page-title', 'Salary & Benefits')

@section('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/benefits.css', 'resources/js/benefits.js'])
@endsection

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $coKey = fn ($code) => 'co-' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code));
    $listUrl = fn (array $changes = []) => route('payroll.benefits.index', array_filter(
        array_merge(['search' => $search, 'company' => $company, 'department' => $department], $changes),
        fn ($v) => $v !== null && $v !== ''
    ));
    // Legacy "Eligibility" column: three months after the hire date
    $eligible = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true)
        ? date('M d, Y', strtotime('+3 months', strtotime($d))) : '—';
    $typeLabels = \App\Services\EmployeeBenefitsService::SALARY_TYPES;
@endphp

<div class="dash bf">

    {{-- ===================== Header ===================== --}}
    <div class="section-head">
        <div>
            <h2>Salary &amp; Benefits</h2>
            <div class="sub">Salary rate, salary type, contributions and bank details payroll uses for each employee.</div>
        </div>
        <a href="{{ route('payroll.benefits.history') }}" class="bf-btn bf-btn-ghost">
            <i class="fa-solid fa-clock-rotate-left"></i> Change history
        </a>
    </div>

    {{-- ===================== Stats (selected company) ===================== --}}
    <div class="stats bf-stats">
        <div class="stat reveal">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-label">Active employees · {{ $company }}</div>
            <div class="stat-value">{{ number_format($stats->total ?? 0) }}</div>
            <div class="stat-foot">{{ number_format($stats->rated ?? 0) }} Rated · {{ number_format($stats->fixed ?? 0) }} Fixed / Daily</div>
        </div>
        <div class="stat reveal {{ ($stats->no_salary ?? 0) > 0 ? 'bf-stat-warn' : '' }}">
            <div class="stat-icon"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="stat-label">No salary rate</div>
            <div class="stat-value">{{ number_format($stats->no_salary ?? 0) }}</div>
            <div class="stat-foot">Rate is blank or 0.00</div>
        </div>
        <div class="stat reveal {{ ($stats->no_account ?? 0) > 0 ? 'bf-stat-warn' : '' }}">
            <div class="stat-icon"><i class="fa-solid fa-building-columns"></i></div>
            <div class="stat-label">No bank account</div>
            <div class="stat-value">{{ number_format($stats->no_account ?? 0) }}</div>
            <div class="stat-foot">Excluding employees paid in cash</div>
        </div>
    </div>

    {{-- ===================== Filters ===================== --}}
    <form method="GET" action="{{ route('payroll.benefits.index') }}" class="bf-toolbar" id="bfSearchForm">
        <input type="hidden" name="company" value="{{ $company }}">
        <div class="bf-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search by employee ID or name…"
                   aria-label="Search by employee ID or name" autocomplete="off" data-autosearch>
        </div>
        @if ($search !== '')
            <a href="{{ route('payroll.benefits.index', ['company' => $company]) }}" class="bf-btn bf-btn-ghost"><i class="fa-solid fa-xmark"></i> Clear</a>
        @endif
    </form>

    <div class="bf-tabs" role="tablist" aria-label="Company">
        @foreach ($companies as $c)
            <a href="{{ $listUrl(['company' => $c['code'], 'department' => null]) }}" role="tab"
               aria-selected="{{ $company === $c['code'] ? 'true' : 'false' }}" title="{{ $c['name'] }}"
               class="bf-tab {{ $coKey($c['code']) }} {{ $company === $c['code'] ? 'is-active' : '' }} {{ $search !== '' && !$c['count'] ? 'is-dim' : '' }}">
                {{ $c['code'] }} <span class="bf-count">{{ number_format($c['count']) }}</span>
            </a>
        @endforeach
    </div>

    @if ($departments->isNotEmpty())
        <div class="bf-pills" role="tablist" aria-label="Department">
            <a href="{{ $listUrl(['department' => null]) }}" class="bf-pill {{ $department === null ? 'is-active' : '' }}">
                All <span class="bf-count">{{ number_format($departments->sum('n')) }}</span>
            </a>
            @foreach ($departments as $d)
                <a href="{{ $listUrl(['department' => $d->id]) }}" class="bf-pill {{ $department === (int) $d->id ? 'is-active' : '' }}">
                    {{ $d->department }} <span class="bf-count">{{ number_format($d->n) }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ===================== Table ===================== --}}
    <div class="company bf-card {{ $coKey($company) }}">
        <div class="bf-table-wrap">
            <table class="bank-table bf-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Eligibility</th>
                        <th>Type</th>
                        <th class="num">SSS</th>
                        <th class="num">PhilHealth</th>
                        <th class="num">Pag-IBIG</th>
                        <th class="num">Salary Rate</th>
                        <th>Bank</th>
                        <th class="go"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $e)
                        @php
                            $type = $e->salary_type ?: 'Rated';
                            $tone = match (true) {
                                in_array($type, ['Fixed', 'Daily'], true) => 'bf-row-blue',
                                $e->startshift && in_array(substr($e->startshift, 0, 5), $graveyard, true) => 'bf-row-gray',
                                default => '',
                            };
                            $suffix = in_array(strtoupper(trim((string) $e->suffix)), ['', 'N/A'], true) ? '' : ' ' . trim($e->suffix);
                            $editUrl = route('payroll.benefits.edit', ['idno' => $e->idno] + array_filter(['search' => $search]));
                        @endphp
                        <tr class="clickable {{ $tone }}" data-href="{{ $editUrl }}">
                            <td class="bf-id">{{ $e->idno }}</td>
                            <td class="strong"><a href="{{ $editUrl }}">{{ $e->lastname }}</a>, {{ $e->firstname }}{{ $suffix }}</td>
                            <td>{{ $eligible($e->dateofhired) }}</td>
                            <td><span class="bf-type bf-type-{{ strtolower($type) }}">{{ $typeLabels[$type] ?? $type }}</span></td>
                            <td class="num">{{ $money($e->sss) }}</td>
                            <td class="num">{{ $money($e->phic) }}</td>
                            <td class="num">{{ $money($e->hdmf) }}</td>
                            <td class="num strong {{ (float) $e->salary <= 0 ? 'bf-missing' : '' }}">{{ $money($e->salary) }}</td>
                            <td>{{ $e->bank ?: '—' }}</td>
                            <td class="go"><i class="fa-solid fa-chevron-right"></i></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="bf-empty-row">No employees match your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bf-foot">
        <span class="bf-legend">
            <span class="bf-swatch bf-row-blue"></span> Fixed / Fixed Daily
            <span class="bf-swatch bf-row-gray"></span> Graveyard shift
        </span>
        {{ $rows->onEachSide(1)->links('benefits.partials.pagination') }}
    </div>
</div>
@endsection
