@extends('layouts.app')

@section('title', 'Movement Tracker - Accounting Portal')
@section('page-title', 'Movement Tracker')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
@php
    $fmt = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($d)) : '-';
@endphp

<div class="hr">
    @include('hr.partials.tracker-head', [
        'route'     => 'hr.movement-tracker',
        'dates'     => true,
        'dateLabel' => 'Effectivity',
        'total'     => $rows->total(),
        'unit'      => 'movement',
    ])

    <div class="hr-table-wrap" id="hrTableWrap">
        <table class="hr-table hr-table--grouped">
            <thead>
                <tr>
                    <th rowspan="2" class="text-center">No.</th>
                    <th rowspan="2">ID</th>
                    <th rowspan="2">Employee Name</th>
                    <th colspan="2" class="text-center hr-group">Company</th>
                    <th colspan="2" class="text-center hr-group">Department</th>
                    <th colspan="2" class="text-center hr-group">Job Position</th>
                    <th colspan="2" class="text-center hr-group">Shift</th>
                    <th rowspan="2" class="hr-group-start">Effectivity</th>
                </tr>
                <tr>
                    @foreach (range(1, 4) as $_)
                        <th class="hr-group-start">From</th>
                        <th>To</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $e)
                    <tr>
                        <td class="text-center hr-muted">{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="hr-id">{{ $e->idno }}</td>
                        @include('hr.partials.tracker-name')
                        <td class="hr-wrap hr-group-start">{{ $e->company_from }}</td>
                        <td class="hr-wrap">{{ $e->company_to }}</td>
                        <td class="hr-wrap hr-group-start">{{ $e->dept_from }}</td>
                        <td class="hr-wrap">{{ $e->dept_to }}</td>
                        <td class="hr-wrap hr-group-start">{{ $e->job_from }}</td>
                        <td class="hr-wrap">{{ $e->job_to }}</td>
                        <td class="hr-date hr-group-start">{{ $e->shift_from }}</td>
                        <td class="hr-date">{{ $e->shift_to }}</td>
                        <td class="hr-date hr-group-start">{{ $fmt($e->effectivitydate) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="hr-empty">No movements match your filters.</td>
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
