@extends('layouts.app')

@section('title', 'Leave Application - Accounting Portal')
@section('page-title', 'Leave Application')

@section('styles')
    @vite(['resources/css/leaves.css', 'resources/js/leaves.js'])
@endsection

@section('content')
<div class="lv">

    {{-- ===================== Filters ===================== --}}
    <div class="lv-toolbar">
        <form method="GET" action="{{ route('leaves.applications') }}" class="lv-filters">
            <div class="lv-field">
                <label for="year">Year</label>
                <select name="year" id="year" onchange="this.form.from.value=''; this.form.to.value=''; this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}{{ $y === (int) now('Asia/Manila')->year ? ' (This year)' : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lv-field">
                <label for="from">From</label>
                <input type="date" name="from" id="from" value="{{ $from }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31">
            </div>

            <div class="lv-field">
                <label for="to">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31">
            </div>

            <div class="lv-actions">
                <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="{{ route('leaves.applications', ['year' => $year]) }}">Reset</a>
            </div>
        </form>

        {{-- Search jumps to the matching row, switching company/department tabs as needed --}}
        <div class="lv-search" role="search">
            <label for="lvSearch">Find employee</label>
            <div class="lv-search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="lvSearch" placeholder="Name or employee ID..." autocomplete="off">
                <span class="lv-search-count" id="lvSearchCount" aria-live="polite"></span>
                <button type="button" id="lvPrev" title="Previous match (Shift+Enter)" aria-label="Previous match" disabled><i class="fa-solid fa-chevron-up"></i></button>
                <button type="button" id="lvNext" title="Next match (Enter)" aria-label="Next match" disabled><i class="fa-solid fa-chevron-down"></i></button>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="lv-alert" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}
        </div>
    @endif

    <div class="lv-range">
        <span>
            <i class="fa-regular fa-calendar"></i>
            {{ date('M d, Y', strtotime($rangeFrom)) }} – {{ date('M d, Y', strtotime($rangeTo)) }}
        </span>
        <span><strong>{{ number_format($total) }}</strong> leave {{ Str::plural('application', $total) }}</span>
    </div>

    {{-- ===================== Company tabs ===================== --}}
    <div class="lv-tabs lv-company-tabs" role="tablist" aria-label="Companies">
        @foreach ($tabs as $i => $company)
            <button type="button"
                    class="lv-tab {{ $i === 0 ? 'is-active' : '' }}"
                    role="tab"
                    aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                    data-company="{{ $company['code'] }}"
                    title="{{ $company['name'] }}">
                {{ $company['code'] }}
                <span class="lv-count">{{ number_format($company['count']) }}</span>
            </button>
        @endforeach
    </div>

    @foreach ($tabs as $i => $company)
        <section class="lv-company {{ $i === 0 ? 'is-active' : '' }}" data-company-pane="{{ $company['code'] }}" role="tabpanel">

            @if ($company['departments']->isEmpty())
                <div class="lv-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    <p>No leave applications for {{ $company['code'] }} in this range.</p>
                </div>
            @else
                {{-- Department tabs --}}
                <div class="lv-tabs lv-dept-tabs" role="tablist" aria-label="{{ $company['code'] }} departments">
                    @foreach ($company['departments'] as $j => $dept)
                        <button type="button"
                                class="lv-tab {{ $j === 0 ? 'is-active' : '' }}"
                                role="tab"
                                aria-selected="{{ $j === 0 ? 'true' : 'false' }}"
                                data-dept="{{ $dept['key'] }}">
                            {{ $dept['name'] }}
                            <span class="lv-count">{{ number_format($dept['count']) }}</span>
                            <span class="lv-match-dot" aria-hidden="true"></span>
                        </button>
                    @endforeach
                </div>

                @foreach ($company['departments'] as $j => $dept)
                    <div class="lv-dept {{ $j === 0 ? 'is-active' : '' }}" data-dept-pane="{{ $dept['key'] }}" role="tabpanel">
                        <div class="lv-table-wrap">
                            <table class="lv-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th class="num">Days</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Reason</th>
                                        <th>Applied On</th>
                                        <th>Status</th>
                                        <th>HR's Notes</th>
                                        <th>Approvers Notes</th>
                                    </tr>
                                </thead>
                                {{-- Rows are drawn by leaves.js from #leaveData when the tab is opened --}}
                                <tbody data-dept-body="{{ $dept['key'] }}"></tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif

        </section>
    @endforeach

</div>

<script type="application/json" id="leaveData">@json($tabs->flatMap(fn ($c) => $c['departments']->mapWithKeys(fn ($d) => [$d['key'] => ['company' => $c['code'], 'rows' => $d['rows']]])))</script>
@endsection
