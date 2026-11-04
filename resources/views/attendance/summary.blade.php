@extends('layouts.app')

@section('title', 'Attendance Summary - Accounting Portal')
@section('page-title', 'Attendance Summary')

@section('styles')
    @vite(['resources/css/attendance.css'])
@endsection

@section('content')
@php
    $incidents = \App\Services\AttendanceSummaryReport::INCIDENTS;
    $totals = \App\Services\AttendanceSummaryReport::TOTALS;
@endphp

<div class="att att-sum-page">

    {{-- ===================== Header: company + incident legend ===================== --}}
    <div class="att-sum-header">
        <div class="att-sum-company">
            <span class="att-sum-company-name">{{ $companyName }}</span>
            <span class="att-sum-company-range">{{ date('M d, Y', strtotime($from)) }} - {{ date('M d, Y', strtotime($to)) }}</span>
            @if ($deptName)
                <span class="att-sum-kind">{{ $deptName }}</span>
            @endif
            @if ($monthRange)
                <span class="att-sum-month-badge">{{ $monthRange }}</span>
            @endif
        </div>

        <table class="att-sum-legend">
            <thead>
                <tr>
                    <th>Incident</th><th class="att-sum-legend-pts">Points</th><th class="att-sum-legend-code">Code</th>
                    <th>Incident</th><th class="att-sum-legend-pts">Points</th><th class="att-sum-legend-code">Code</th>
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Services\AttendanceSummaryReport::LEGEND as $pair)
                    <tr>
                        @foreach ($pair as $item)
                            @if ($item)
                                <td>{{ $item[0] }}</td>
                                <td class="att-sum-legend-pts">{{ $item[1] }}</td>
                                <td class="att-sum-legend-code">{{ $item[2] }}</td>
                            @else
                                <td colspan="3"></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ===================== Controls ===================== --}}
    @include('attendance.partials.summary-controls', ['action' => route('attendance.summary')])

    {{-- ===================== Day grid ===================== --}}
    <div class="att-table-wrap att-sum-wrapper">
        <table class="att-table att-sum-table">
            <thead>
                <tr>
                    <th colspan="3" class="att-sum-sticky att-sum-info">Employee Information</th>
                    @foreach ($dates as $d)
                        <th class="att-sum-day {{ $d->sunday ? 'is-sunday' : '' }} {{ $d->monthStart ? 'att-sum-month-start' : '' }}">
                            @if ($d->monthStart) <span class="att-sum-mon">{{ $d->monthShort }}</span> @endif
                            {{ $d->day }}
                        </th>
                    @endforeach
                    <th colspan="{{ count($incidents) }}" class="att-sum-group">Summary</th>
                    <th colspan="{{ count($totals) }}" class="att-sum-group">Total</th>
                </tr>
                <tr>
                    <th class="att-sum-sticky att-sum-c1"><div class="att-sum-box">No.</div></th>
                    <th class="att-sum-sticky att-sum-c2"><div class="att-sum-box">Employee Name</div></th>
                    <th class="att-sum-sticky att-sum-c3">Department</th>
                    @foreach ($dates as $d)
                        <th class="att-sum-day {{ $d->sunday ? 'is-sunday' : '' }} {{ $d->monthStart ? 'att-sum-month-start' : '' }}">{{ $d->weekday }}</th>
                    @endforeach
                    @foreach ($incidents as $label)
                        <th class="att-sum-num {{ $loop->first ? 'att-sum-group' : '' }}">{{ $label }}</th>
                    @endforeach
                    @foreach ($totals as $label)
                        <th class="att-sum-num {{ $loop->first ? 'att-sum-group' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $emp)
                    @php $rowTint = $emp->isFixed ? 'att-sum-fixed' : ($emp->isGy ? 'att-sum-gy' : ''); @endphp
                    <tr class="{{ $emp->isResigned ? 'att-sum-resigned' : '' }}">
                        <td class="att-sum-sticky att-sum-c1 {{ $rowTint }}"><div class="att-sum-box">{{ $loop->iteration }}.</div></td>
                        <td class="att-sum-sticky att-sum-c2 {{ $rowTint }}"><div class="att-sum-box">
                            <strong>{{ $emp->lastname }}</strong>, {{ $emp->given }}
                            @if ($emp->isResigned)
                                <span class="att-sum-res">(Resigned{{ $emp->dateresigned ? ': '.date('m/d/Y', strtotime($emp->dateresigned)) : '' }})</span>
                            @endif</div>
                        </td>
                        <td class="att-sum-sticky att-sum-c3 {{ $emp->deptColor ? 'att-sum-dept' : '' }}" @if ($emp->deptColor) style="--dept: {{ $emp->deptColor }}" @endif>{{ $emp->dept_name }}</td>
                        {{-- One line per day: this markup repeats for every day of every employee. --}}
                        @foreach ($dates as $d)@php $c = $emp->cells[$d->date]; @endphp<td class="att-sum-cell{{ $c ? ' att-tone att-tone--'.$c->tone : '' }}{{ $d->sunday ? ' is-sunday' : '' }}{{ $d->monthStart ? ' att-sum-month-start' : '' }}"@if ($c) title="{{ $c->tip }}"@endif>{{ $c?->text }}</td>@endforeach
                        @foreach (array_keys($incidents) as $key)<td class="att-sum-num{{ $loop->first ? ' att-sum-group' : '' }}">{{ $emp->totals[$key] }}</td>@endforeach
                        @foreach (array_keys($totals) as $key)<td class="att-sum-num{{ $loop->first ? ' att-sum-group' : '' }}">{{ $emp->totals[$key] }}</td>@endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + count($dates) + count($incidents) + count($totals) }}" class="att-sum-empty">
                            {{ $search !== '' || $am || $gy || $fixed ? 'No employees match the search and filters.' : 'No record found!' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
