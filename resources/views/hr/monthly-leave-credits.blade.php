@extends('layouts.app')

@section('title', 'Monthly Leave Credits - Accounting Portal')
@section('page-title', 'Monthly Leave Credits')

@section('styles')
    @vite(['resources/css/leaves.css', 'resources/js/leave-credits.js'])
@endsection

@section('content')
@php
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create()->month($m)->format('F')]);
    $prevMonth = $month === 1 ? 12 : $month - 1;
    $nextMonth = $month === 12 ? 1 : $month + 1;
@endphp

{{-- No data-used-url: Used figures are previous-period totals, shown as plain numbers --}}
<div class="lv lc" data-profile-url="{{ route('hr.roster.show', ['idno' => 'IDNO']) }}">

    {{-- ===================== Filters ===================== --}}
    <div class="lv-toolbar">
        <form method="GET" action="{{ route('hr.monthly-leave-credits') }}" class="lv-filters lc-filters">
            <input type="hidden" name="month" value="{{ $month }}">

            {{-- Leave period start month; disabled while the eligibility filter is on --}}
            <div class="lv-field">
                <label>Period starts in</label>
                <div class="lc-month {{ $filtered ? 'is-disabled' : '' }}">
                    @if ($filtered)
                        <span class="lc-month-btn" aria-disabled="true"><i class="fa-solid fa-chevron-left"></i></span>
                        <strong>{{ $monthNames[$month] }}</strong>
                        <span class="lc-month-btn" aria-disabled="true"><i class="fa-solid fa-chevron-right"></i></span>
                    @else
                        <a class="lc-month-btn" href="{{ route('hr.monthly-leave-credits', ['month' => $prevMonth]) }}" title="{{ $monthNames[$prevMonth] }}" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></a>
                        <strong>{{ $monthNames[$month] }}</strong>
                        <a class="lc-month-btn" href="{{ route('hr.monthly-leave-credits', ['month' => $nextMonth]) }}" title="{{ $monthNames[$nextMonth] }}" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></a>
                    @endif
                </div>
            </div>

            {{-- Eligibility date, month and day only (no year); a range may cross the new year --}}
            <div class="lv-field">
                <label>Eligibility from</label>
                <div class="lc-md">
                    <select name="from_month" aria-label="Eligibility from month">
                        <option value="">Month</option>
                        @foreach ($monthNames as $num => $name)
                            <option value="{{ $num }}" @selected((int) $fromMonth === $num)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <select name="from_day" aria-label="Eligibility from day">
                        <option value="">Day</option>
                        @foreach (range(1, 31) as $d)
                            <option value="{{ $d }}" @selected((int) $fromDay === $d)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="lv-field">
                <label>Eligibility to</label>
                <div class="lc-md">
                    <select name="to_month" aria-label="Eligibility to month">
                        <option value="">Month</option>
                        @foreach ($monthNames as $num => $name)
                            <option value="{{ $num }}" @selected((int) $toMonth === $num)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <select name="to_day" aria-label="Eligibility to day">
                        <option value="">Day</option>
                        @foreach (range(1, 31) as $d)
                            <option value="{{ $d }}" @selected((int) $toDay === $d)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="lv-actions">
                <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="{{ route('hr.monthly-leave-credits', ['month' => $month]) }}">Reset</a>
            </div>
        </form>

        {{-- Search filters to matching rows, hiding tabs with no matches and jumping to the first one --}}
        <div class="lv-search" role="search">
            <label for="lvSearch">Search</label>
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
            <i class="fa-solid fa-calendar-check"></i>
            Previous period VL / SL / PTO ·
            @if ($filtered)
                Eligibility
                {{ $fromMonth && $fromDay ? $monthNames[(int) $fromMonth] . ' ' . (int) $fromDay : '…' }}
                –
                {{ $toMonth && $toDay ? $monthNames[(int) $toMonth] . ' ' . (int) $toDay : '…' }}
            @else
                Period starts in {{ $monthNames[$month] }}
            @endif
        </span>
        <span><strong>{{ number_format($total) }}</strong> {{ Str::plural('employee', $total) }}</span>
    </div>

    @include('hr.partials.leave-credit-tabs', ['emptyNote' => $filtered ? ' with that eligibility date' : ' with a period starting in ' . $monthNames[$month]])

</div>

@endsection
