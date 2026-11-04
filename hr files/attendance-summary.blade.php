@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endpush
@php
    $incidents = \App\Services\AttendanceSummaryReport::INCIDENTS;
    $totals = \App\Services\AttendanceSummaryReport::TOTALS;
@endphp
<div class="att-container att-sum-page">
    @section('title', 'Attendance Summary')

    {{-- ==================== HEADER: company + incident legend ==================== --}}
    <div class="att-sum-header">
        <div class="att-sum-company">
            <span class="att-sum-company-name">{{ $companyName }}</span>
            <span class="att-sum-company-range">{{ date('M d, Y', strtotime($startDate)) }} - {{ date('M d, Y', strtotime($endDate)) }}</span>
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
                @foreach (\App\Services\AttendanceSummaryReport::LEGEND as $i => $pair)
                    <tr wire:key="legend-{{ $i }}">
                        @foreach ($pair as $j => $item)
                            @if ($item)
                                <td wire:key="legend-{{ $i }}-{{ $j }}">{{ $item[0] }}</td>
                                <td class="att-sum-legend-pts">{{ $item[1] }}</td>
                                <td class="att-sum-legend-code">{{ $item[2] }}</td>
                            @else
                                <td colspan="3" wire:key="legend-{{ $i }}-{{ $j }}"></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ==================== CONTROLS ==================== --}}
    <div class="att-sum-controls">
        <input type="search" wire:model.live.debounce.300ms="search" class="hr-input att-sum-search" placeholder="Search employee..." aria-label="Search employee">

        <button type="button" class="hr-btn" wire:click="$refresh" wire:loading.attr="disabled">Refresh</button>

        <form wire:submit="applyRange" class="att-sum-filter">
            <span class="hr-label">Date Range</span>
            <label class="hr-label" for="sum-from">From</label>
            <input id="sum-from" type="date" wire:model="fromDate" class="hr-input @error('fromDate') is-invalid @enderror" required>
            <label class="hr-label" for="sum-to">To</label>
            <input id="sum-to" type="date" wire:model="toDate" class="hr-input @error('toDate') is-invalid @enderror" required>
            <button type="submit" class="hr-btn hr-btn-primary" wire:loading.attr="disabled" wire:target="applyRange">Apply</button>
        </form>

        <label class="att-legend-check">
            <input type="checkbox" wire:model.live="onlyAm"> Morning Shift
        </label>
        <label class="att-legend-check">
            <input type="checkbox" wire:model.live="onlyGy"> Graveyard
        </label>
        <label class="att-legend-check">
            <input type="checkbox" wire:model.live="onlyFixed"> Fixed / Daily
        </label>

        <a href="{{ $exportUrl }}" class="hr-btn hr-btn-success att-sum-download" @if ($employees->isEmpty()) aria-disabled="true" tabindex="-1" @endif>Download</a>
    </div>

    @error('fromDate') <p class="hr-error att-sum-note">{{ $message }}</p> @enderror
    @error('toDate') <p class="hr-error att-sum-note">{{ $message }}</p> @enderror
    @if ($rangeCapped)
        <p class="hr-warn att-sum-note">Showing the first {{ \App\Livewire\Attendance\AttendanceSummary::MAX_DAYS }} days only. Pick a shorter range to see the rest.</p>
    @endif

    {{-- ==================== TABLE ==================== --}}
    <div class="att-table-wrapper att-sum-wrapper" wire:loading.class="is-loading" wire:target="search, onlyAm, onlyFixed, onlyGy, applyRange, $refresh">
        <table class="att-table att-sum-table">
            <thead>
                <tr>
                    <th colspan="3" class="att-sum-sticky att-sum-info">Employee Information</th>
                    @foreach ($dates as $d)
                        <th class="att-sum-day {{ $d->sunday ? 'is-sunday' : '' }} {{ $d->monthStart ? 'att-sum-month-start' : '' }}" wire:key="hd-{{ $d->date }}">
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
                        <th class="att-sum-day {{ $d->sunday ? 'is-sunday' : '' }} {{ $d->monthStart ? 'att-sum-month-start' : '' }}" wire:key="hw-{{ $d->date }}">{{ $d->weekday }}</th>
                    @endforeach
                    @foreach ($incidents as $key => $label)
                        <th class="att-sum-num {{ $loop->first ? 'att-sum-group' : '' }}" wire:key="hi-{{ $key }}">{{ $label }}</th>
                    @endforeach
                    @foreach ($totals as $key => $label)
                        <th class="att-sum-num {{ $loop->first ? 'att-sum-group' : '' }}" wire:key="ht-{{ $key }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $emp)
                    @php $rowTint = $emp->isFixed ? 'att-sum-fixed' : ($emp->isGy ? 'att-sum-gy' : ''); @endphp
                    <tr wire:key="sum-{{ $emp->idno }}" class="{{ $emp->isResigned ? 'att-sum-resigned' : '' }}">
                        <td class="att-sum-sticky att-sum-c1 {{ $rowTint }}"><div class="att-sum-box">{{ $loop->iteration }}.</div></td>
                        <td class="att-sum-sticky att-sum-c2 {{ $rowTint }}"><div class="att-sum-box">
                            <strong>{{ $emp->lastname }}</strong>, {{ $emp->given }}
                            @if ($emp->isResigned)
                                <span class="att-sum-res">(Resigned{{ $emp->dateresigned ? ': '.date('m/d/Y', strtotime($emp->dateresigned)) : '' }})</span>
                            @endif</div>
                        </td>
                        <td class="att-sum-sticky att-sum-c3 {{ $emp->deptColor ? 'att-sum-dept' : '' }}" @if ($emp->deptColor) style="--dept: {{ $emp->deptColor }}" @endif>{{ $emp->dept_name }}</td>
                        {{-- One line per day: this markup repeats for every day of every employee. --}}
                        @foreach ($dates as $d)@php $c = $emp->cells[$d->date]; @endphp<td wire:key="c-{{ $emp->idno }}-{{ $loop->index }}" class="att-sum-cell{{ $c ? ' att-tone att-tone--'.$c->tone : '' }}{{ $d->sunday ? ' is-sunday' : '' }}{{ $d->monthStart ? ' att-sum-month-start' : '' }}"@if ($c) title="{{ $c->tip }}"@endif>{{ $c?->text }}</td>@endforeach
                        @foreach (array_keys($incidents) as $key)<td wire:key="i-{{ $emp->idno }}-{{ $key }}" class="att-sum-num{{ $loop->first ? ' att-sum-group' : '' }}">{{ $emp->totals[$key] }}</td>@endforeach
                        @foreach (array_keys($totals) as $key)<td wire:key="t-{{ $emp->idno }}-{{ $key }}" class="att-sum-num{{ $loop->first ? ' att-sum-group' : '' }}">{{ $emp->totals[$key] }}</td>@endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + count($dates) + count($incidents) + count($totals) }}" class="att-empty att-sum-empty">No record found!</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
