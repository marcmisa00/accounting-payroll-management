@extends('layouts.app')

@section('title', 'Benefits History - Accounting Portal')
@section('page-title', 'Benefits History')

@section('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/benefits.css', 'resources/js/benefits.js'])
@endsection

@section('content')
<div class="dash bf">

    <div class="section-head">
        <div>
            <h2>Benefits History</h2>
            <div class="sub">Every save of salary, benefits and bank details, newest first. Account numbers and OTP secrets are never shown.</div>
        </div>
        <a href="{{ route('payroll.benefits.index') }}" class="bf-btn bf-btn-ghost"><i class="fa-solid fa-hand-holding-dollar"></i> Salary &amp; Benefits</a>
    </div>

    <form method="GET" action="{{ route('payroll.benefits.history') }}" class="bf-toolbar bf-filters">
        <div class="bf-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Employee ID or name…" aria-label="Employee ID or name" autocomplete="off">
        </div>
        <div class="select-wrap">
            <select name="user" class="pick" aria-label="Changed by">
                <option value="">Anyone</option>
                @foreach ($users as $u)
                    <option value="{{ $u }}" @selected(($filters['user'] ?? '') === $u)>{{ $u }}</option>
                @endforeach
            </select>
        </div>
        <label class="bf-inline">From <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="bf-inline">To <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button type="submit" class="bf-btn bf-btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
        @if (array_filter($filters))
            <a href="{{ route('payroll.benefits.history') }}" class="bf-btn bf-btn-ghost"><i class="fa-solid fa-xmark"></i> Reset</a>
        @endif
        <span class="bf-total"><strong>{{ number_format($entries->total()) }}</strong> {{ Str::plural('change', $entries->total()) }}</span>
    </form>

    @forelse ($entries as $entry)
        @include('benefits.partials.entry', ['entry' => $entry])
    @empty
        <div class="empty"><i class="fa-regular fa-folder-open"></i><div>No changes match your filters.</div></div>
    @endforelse

    <div class="bf-foot">
        <span></span>
        {{ $entries->onEachSide(1)->links('benefits.partials.pagination') }}
    </div>
</div>
@endsection
