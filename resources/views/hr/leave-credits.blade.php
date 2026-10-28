@extends('layouts.app')

@section('title', 'Leave Credits - Accounting Portal')
@section('page-title', 'Leave Credits')

@section('styles')
    @vite(['resources/css/leaves.css', 'resources/js/leave-credits.js'])
@endsection

@section('content')
@php
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create()->month($m)->format('F')]);
@endphp

<div class="lv lc"
     data-used-url="{{ route('hr.leave-credits.used', ['idno' => 'IDNO']) }}"
     data-profile-url="{{ route('hr.roster.show', ['idno' => 'IDNO']) }}">

    {{-- ===================== Filters ===================== --}}
    <div class="lv-toolbar">
        <form method="GET" action="{{ route('hr.leave-credits') }}" class="lv-filters lc-filters">
            <div class="lv-field">
                <label for="batch">View</label>
                <select name="batch" id="batch" onchange="this.form.submit()">
                    @foreach (\App\Http\Controllers\LeaveCreditController::BATCHES as $key => $b)
                        <option value="{{ $key }}" @selected($batch === $key)>{{ $b['label'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Eligibility date, month and day only (no year) --}}
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
                <a href="{{ route('hr.leave-credits', ['batch' => $batch]) }}">Reset</a>
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
            <i class="fa-solid {{ \App\Http\Controllers\LeaveCreditController::BATCHES[$batch]['icon'] }}"></i>
            {{ \App\Http\Controllers\LeaveCreditController::BATCHES[$batch]['label'] }}
            @if ($filtered)
                · Eligibility
                {{ $fromMonth && $fromDay ? $monthNames[(int) $fromMonth] . ' ' . (int) $fromDay : '…' }}
                –
                {{ $toMonth && $toDay ? $monthNames[(int) $toMonth] . ' ' . (int) $toDay : '…' }}
            @endif
        </span>
        <span><strong>{{ number_format($total) }}</strong> {{ Str::plural('employee', $total) }}</span>
    </div>

    @include('hr.partials.leave-credit-tabs', ['emptyNote' => $filtered ? ' with that eligibility date' : ''])

</div>

@endsection
