@extends('layouts.app')

@section('title', 'Employee Infractions - Accounting Portal')
@section('page-title', 'Employee Infractions')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
@php
    $fmt = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($d)) : '';
    $badge = fn ($s) => match ($s) {
        'pending' => 'hr-badge-warning',
        'Void'    => 'hr-badge-danger',
        default   => 'hr-badge-success',
    };
@endphp

<div class="hr">
    @include('hr.partials.tracker-head', [
        'route'     => 'hr.infractions',
        'total'     => $rows->total(),
        'unit'      => 'infraction',
        'dates'     => true,
        'dateLabel' => 'Date issued',
        'hidden'    => array_filter(['status' => $status, 'department' => $department]),
        'belowTabs' => 'hr.partials.infraction-filters',
    ])

    <div class="hr-table-wrap" id="hrTableWrap">
        <table class="hr-table">
            <thead>
                <tr>
                    <th class="text-center">No.</th>
                    <th>ID</th>
                    <th>Employee Name</th>
                    <th>Job Title</th>
                    <th>Issued On</th>
                    <th>Served On</th>
                    <th>Type of Memo</th>
                    <th>Offense</th>
                    <th>Category</th>
                    <th class="text-center">Points</th>
                    <th>Memo No.</th>
                    <th>Incident</th>
                    <th>Suspension Dates</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $e)
                    <tr>
                        <td class="text-center hr-muted">{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="hr-id">{{ $e->idno }}</td>
                        @include('hr.partials.tracker-name')
                        <td class="hr-wrap" title="{{ $e->jobtitle }}">{{ $e->jobtitle }}</td>
                        <td class="hr-date">{{ $fmt($e->dateissued) }}</td>
                        <td class="hr-date">{{ $fmt($e->dateserved) }}</td>
                        <td class="hr-wrap" title="{{ $e->typeofmemo }}">{{ $e->typeofmemo }}</td>
                        <td class="hr-wrap" title="{{ $e->typeofoffense }}">{{ $e->typeofoffense }}</td>
                        <td>{{ $e->typecat }}</td>
                        <td class="text-center">{{ $e->points }}</td>
                        <td class="hr-date">{{ $e->memonumber }}</td>
                        <td class="hr-wrap" title="{{ $e->dateofincident }}">{{ $e->dateofincident }}</td>
                        <td class="hr-wrap" title="{{ $e->dateofsuspension }}">{{ $e->dateofsuspension }}</td>
                        <td class="text-center">
                            <span class="hr-badge {{ $badge($e->status) }}">{{ $e->status === 'pending' ? 'Pending' : $e->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="hr-empty">No infractions match your filters.</td>
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
