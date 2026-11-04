@extends('layouts.app')

@section('title', 'Missed Logs / OB Summary - Accounting Portal')
@section('page-title', 'Missed Logs / OB Summary')

@section('styles')
    @vite(['resources/css/attendance.css'])
@endsection

@section('content')
@php
    $meaning = \App\Services\MissedLogsSummaryReport::MEANING;
    $fmtPoints = fn ($p) => \App\Services\MissedLogsSummaryReport::points((float) $p);
@endphp

<div class="att att-sum-page">

    {{-- ===================== Header: company + incident tagging reference ===================== --}}
    <div class="att-sum-header">
        <div class="att-sum-company">
            <span class="att-sum-company-name">{{ $companyName }}</span>
            <span class="att-sum-company-range">{{ date('M d, Y', strtotime($from)) }} - {{ date('M d, Y', strtotime($to)) }}</span>
            <span class="att-sum-kind">Missed Logs / Over-break{{ $deptName ? ' · '.$deptName : '' }}</span>
            @if ($monthRange)
                <span class="att-sum-month-badge">{{ $monthRange }}</span>
            @endif
        </div>

        <table class="att-sum-legend" aria-label="Incident tagging reference">
            <thead>
                <tr>
                    <th>Incident</th><th class="att-sum-legend-pts">Points</th><th class="att-sum-legend-code">Code</th>
                    <th>Incident</th><th class="att-sum-legend-pts">Points</th><th class="att-sum-legend-code">Code</th>
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Services\MissedLogsSummaryReport::LEGEND as $pair)
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

    {{-- ===================== Totals of the rows shown ===================== --}}
    <dl class="att-mis-stats" aria-label="Totals of the employees shown">
        <div><dt>Employees</dt><dd>{{ number_format($stats['employees']) }}</dd></div>
        <div><dt>With incidents</dt><dd>{{ number_format($stats['withIncidents']) }}</dd></div>
        @foreach ($stats['codes'] as $code => $count)
            <div title="{{ $meaning[$code] }}"><dt>{{ $code }}</dt><dd>{{ number_format($count) }}</dd></div>
        @endforeach
        <div><dt>Points</dt><dd>{{ $fmtPoints($stats['points']) }}</dd></div>
    </dl>

    {{-- ===================== Controls ===================== --}}
    @include('attendance.partials.summary-controls', ['action' => route('attendance.missed-summary'), 'withIncidents' => true])

    {{-- ===================== Day grid ===================== --}}
    <div class="att-table-wrap att-sum-wrapper" id="misGrid">
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
                    <th colspan="{{ count($codes) + 2 }}" class="att-sum-group">Summary</th>
                </tr>
                <tr>
                    <th class="att-sum-sticky att-sum-c1"><div class="att-sum-box">No.</div></th>
                    <th class="att-sum-sticky att-sum-c2"><div class="att-sum-box">Employee Name</div></th>
                    <th class="att-sum-sticky att-sum-c3">Department</th>
                    @foreach ($dates as $d)
                        <th class="att-sum-day {{ $d->sunday ? 'is-sunday' : '' }} {{ $d->monthStart ? 'att-sum-month-start' : '' }}">{{ $d->weekday }}</th>
                    @endforeach
                    @foreach ($codes as $code)
                        <th class="att-sum-num {{ $loop->first ? 'att-sum-group' : '' }}" title="{{ $meaning[$code] }}">{{ $code }}</th>
                    @endforeach
                    <th class="att-sum-num att-sum-group">Total</th>
                    <th class="att-sum-num">Points</th>
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
                        @foreach ($dates as $d)@php $c = $emp->cells[$d->date]; @endphp<td class="att-sum-cell{{ $c ? ' att-tone att-tone--'.$c->tone : '' }}{{ $d->sunday ? ' is-sunday' : '' }}{{ $d->monthStart ? ' att-sum-month-start' : '' }}"@if ($c) tabindex="0" data-tip="{{ json_encode($c->tip) }}" aria-label="{{ $c->text.': '.collect($c->tip)->map(fn ($t) => $t[0].' '.$t[1])->join(', ') }}"@endif>{{ $c?->text }}</td>@endforeach
                        @foreach ($codes as $code)<td class="att-sum-num{{ $loop->first ? ' att-sum-group' : '' }}{{ $emp->totals[$code] ? '' : ' att-sum-zero' }}">{{ $emp->totals[$code] }}</td>@endforeach
                        <td class="att-sum-num att-sum-group att-sum-strong{{ $emp->totals['total'] ? '' : ' att-sum-zero' }}">{{ $emp->totals['total'] }}</td>
                        <td class="att-sum-num att-sum-strong{{ $emp->totals['points'] ? '' : ' att-sum-zero' }}">{{ $fmtPoints($emp->totals['points']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + count($dates) + count($codes) + 2 }}" class="att-sum-empty">
                            {{ $incidents || $search !== '' || $am || $gy ? 'No employees match the search and filters.' : 'No record found!' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- One shared hover card for the day cells (lunch out / in and points, aligned in columns). --}}
    <div class="att-tip" id="misTip" role="tooltip" hidden></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const grid = document.getElementById('misGrid');
    const tip = document.getElementById('misTip');
    if (! grid || ! tip) return;

    function open(td) {
        if (! td) { tip.hidden = true; return; }
        tip.replaceChildren(...JSON.parse(td.dataset.tip).map(function (row) {
            const line = document.createElement('div');
            line.className = 'att-tip-row';
            [[row[0], 'att-tip-label'], [':', 'att-tip-sep'], [row[1], 'att-tip-value']].forEach(function ([text, cls]) {
                const span = document.createElement('span');
                span.className = cls;
                span.textContent = text;
                line.appendChild(span);
            });
            return line;
        }));
        const r = td.getBoundingClientRect();
        const below = r.top < 140;
        tip.classList.toggle('is-below', below);
        tip.style.left = Math.min(Math.max(r.left + r.width / 2, 90), window.innerWidth - 90) + 'px';
        tip.style.top = (below ? r.bottom : r.top) + 'px';
        tip.hidden = false;
    }

    grid.addEventListener('mouseover', function (e) { open(e.target.closest('[data-tip]')); });
    grid.addEventListener('focusin', function (e) { open(e.target.closest('[data-tip]')); });
    grid.addEventListener('mouseleave', function () { tip.hidden = true; });
    grid.addEventListener('focusout', function () { tip.hidden = true; });
    grid.addEventListener('scroll', function () { tip.hidden = true; });
});
</script>
@endsection
