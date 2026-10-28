@extends('layouts.app')

@section('title', 'Resigned Employees - Accounting Portal')
@section('page-title', 'Resigned Employees')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
@php
    $fmt = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($d)) : '—';
@endphp

<div class="hr">
    @include('hr.partials.tracker-head', [
        'route'          => 'hr.resigned',
        'total'          => $rows->total(),
        'unit'           => 'employee',
        'hidden'         => $perPage !== \App\Http\Controllers\HrTrackerController::RESIGNED_PER_PAGE[0] ? ['per_page' => $perPage] : [],
        'perPageOptions' => \App\Http\Controllers\HrTrackerController::RESIGNED_PER_PAGE,
    ])

    <div class="hr-table-wrap" id="hrTableWrap">
        <table class="hr-table hr-table--narrow">
            <thead>
                <tr>
                    <th class="text-center">No.</th>
                    <th>ID</th>
                    <th>Employee Name</th>
                    <th>Date of Birth</th>
                    <th>Job Title</th>
                    <th>Department</th>
                    <th>Date Hired</th>
                    <th>Date Resigned</th>
                    <th>Reason</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $e)
                    <tr>
                        <td class="text-center hr-muted">{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="hr-id">{{ $e->idno }}</td>
                        @include('hr.partials.tracker-name')
                        <td class="hr-date">{{ $fmt($e->birthdate) }}</td>
                        <td class="hr-wrap" title="{{ $e->jobtitle }}">{{ $e->jobtitle }}</td>
                        <td class="hr-wrap" title="{{ $e->department }}">{{ $e->department }}</td>
                        <td class="hr-date">{{ $fmt($e->dateofhired) }}</td>
                        <td class="hr-date">{{ $fmt($e->dateresigned) }}</td>
                        <td><span class="hr-reason" title="{{ $e->reason }}">{{ $e->reason ?: '—' }}</span></td>
                        <td class="text-center">
                            <a href="{{ route('hr.resigned.show', ['idno' => $e->idno] + $listContext) }}" class="hr-view-btn">
                                <i class="fa-solid fa-eye"></i> View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="hr-empty">No resigned employees match your filters.</td>
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
