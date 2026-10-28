@extends('layouts.app')

@section('title', 'Work Setup Tracker - Accounting Portal')
@section('page-title', 'Work Setup Tracker')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
@php
    $fmt = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($d)) : '-';
@endphp

<div class="hr">
    @include('hr.partials.tracker-head', [
        'route'     => 'hr.work-setup-tracker',
        'dates'     => true,
        'dateLabel' => 'Effective date',
        'total'     => $rows->total(),
        'unit'      => 'change',
    ])

    <p class="hr-legend">
        <span class="hr-legend-swatch"></span> Takes effect today or on the next working day
    </p>

    <div class="hr-table-wrap" id="hrTableWrap">
        <table class="hr-table hr-table--grouped hr-table--narrow">
            <thead>
                <tr>
                    <th rowspan="2" class="text-center">No.</th>
                    <th rowspan="2">ID</th>
                    <th rowspan="2">Employee Name</th>
                    <th colspan="2" class="text-center hr-group hr-group--location">Location</th>
                    <th colspan="2" class="text-center hr-group hr-group--setup">Work Setup</th>
                    <th rowspan="2" class="hr-group-start">Effective Date</th>
                </tr>
                <tr>
                    <th class="text-center hr-group-start hr-group--location">From</th>
                    <th class="text-center hr-group--location">To</th>
                    <th class="text-center hr-group-start hr-group--setup">From</th>
                    <th class="text-center hr-group--setup">To</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $e)
                    <tr class="{{ $e->is_upcoming ? 'hr-row-upcoming' : '' }}">
                        <td class="text-center hr-muted">{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="hr-id">{{ $e->idno }}</td>
                        @include('hr.partials.tracker-name')
                        <td class="text-center hr-group-start">{{ $e->location_from ?: '-' }}</td>
                        <td class="text-center">{{ $e->location_to ?: '-' }}</td>
                        <td class="text-center hr-group-start">{{ $e->setup_from ?: '-' }}</td>
                        <td class="text-center">{{ $e->setup_to ?: '-' }}</td>
                        <td class="hr-date hr-group-start">{{ $fmt($e->effective_date) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="hr-empty">No work setup changes match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="hr-foot">
        {{ $rows->onEachSide(1)->links('hr.partials.pagination') }}
    </div>
</div>
@endsection
