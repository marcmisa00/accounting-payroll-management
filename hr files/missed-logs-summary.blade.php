@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endpush
@php
    $meaning = \App\Services\MissedLogsSummaryReport::MEANING;
    $fmtPoints = fn ($p) => \App\Services\MissedLogsSummaryReport::points((float) $p);
@endphp
<div class="att-container att-sum-page">
    @section('title', 'Missed Logs / OB Summary')

    {{-- ==================== HEADER: company + incident tagging reference ==================== --}}
    <div class="att-sum-header">
        <div class="att-sum-company">
            <span class="att-sum-company-name">{{ $companyName }}</span>
            <span class="att-sum-company-range">{{ date('M d, Y', strtotime($startDate)) }} - {{ date('M d, Y', strtotime($endDate)) }}</span>
            <span class="att-sum-kind">Missed Logs / Over-break</span>
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
                @foreach (\App\Services\MissedLogsSummaryReport::LEGEND as $i => $pair)
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

    {{-- ==================== TOTALS OF THE ROWS SHOWN ==================== --}}
    <dl class="hr-card att-logs-stats att-mis-stats" aria-label="Totals of the employees shown">
        <div><dt>Employees</dt><dd>{{ number_format($stats['employees']) }}</dd></div>
        <div><dt>With incidents</dt><dd>{{ number_format($stats['withIncidents']) }}</dd></div>
        @foreach ($stats['codes'] as $code => $count)
            <div wire:key="stat-{{ $code }}" title="{{ $meaning[$code] }}"><dt>{{ $code }}</dt><dd>{{ number_format($count) }}</dd></div>
        @endforeach
        <div><dt>Points</dt><dd>{{ $fmtPoints($stats['points']) }}</dd></div>
    </dl>

    {{-- ==================== CONTROLS ==================== --}}
    <div class="att-sum-controls">
        <input type="search" wire:model.live.debounce.300ms="search" class="hr-input att-sum-search" placeholder="Search name or ID..." aria-label="Search employee">

        <button type="button" class="hr-btn" wire:click="$refresh" wire:loading.attr="disabled">Refresh</button>

        <form wire:submit="applyRange" class="att-sum-filter">
            <span class="hr-label">Date Range</span>
            <label class="hr-label" for="mis-from">From</label>
            <input id="mis-from" type="date" wire:model="fromDate" class="hr-input @error('fromDate') is-invalid @enderror" required>
            <label class="hr-label" for="mis-to">To</label>
            <input id="mis-to" type="date" wire:model="toDate" class="hr-input @error('toDate') is-invalid @enderror" required>
            <button type="submit" class="hr-btn hr-btn-primary" wire:loading.attr="disabled" wire:target="applyRange">Apply</button>
        </form>

        <label class="att-legend-check">
            <input type="checkbox" wire:model.live="onlyIncidents"> With incidents only
        </label>
        <label class="att-legend-check">
            <input type="checkbox" wire:model.live="onlyAm"> Morning Shift
        </label>
        <label class="att-legend-check">
            <input type="checkbox" wire:model.live="onlyGy"> Graveyard
        </label>

        <a href="{{ $exportUrl }}" class="hr-btn hr-btn-success att-sum-download" @if ($employees->isEmpty()) aria-disabled="true" tabindex="-1" @endif>Download</a>
    </div>

    @error('fromDate') <p class="hr-error att-sum-note">{{ $message }}</p> @enderror
    @error('toDate') <p class="hr-error att-sum-note">{{ $message }}</p> @enderror
    @if ($rangeCapped)
        <p class="hr-warn att-sum-note">Showing the first {{ \App\Livewire\Attendance\EmployeeLogs::MAX_DAYS }} days only. Pick a shorter range to see the rest.</p>
    @endif

    {{-- ==================== TABLE ==================== --}}
    {{-- One shared hover card for the day cells (lunch out / in and points, aligned in columns). --}}
    <div x-data="{ tip: [], show: false, x: 0, y: 0, below: false,
                   open(td) {
                       if (! td) { this.show = false; return; }
                       this.tip = JSON.parse(td.dataset.tip);
                       const r = td.getBoundingClientRect();
                       this.below = r.top < 140;
                       this.x = Math.min(Math.max(r.left + r.width / 2, 90), window.innerWidth - 90);
                       this.y = this.below ? r.bottom : r.top;
                       this.show = true;
                   } }"
         x-on:mouseover="open($event.target.closest('[data-tip]'))"
         x-on:focusin="open($event.target.closest('[data-tip]'))"
         x-on:mouseleave="show = false"
         x-on:focusout="show = false"
         x-on:scroll.capture="show = false">
    <div class="att-table-wrapper att-sum-wrapper" wire:loading.class="is-loading" wire:target="search, onlyAm, onlyGy, onlyIncidents, applyRange, $refresh">
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
                    <th colspan="{{ count($codes) + 2 }}" class="att-sum-group">Summary</th>
                </tr>
                <tr>
                    <th class="att-sum-sticky att-sum-c1"><div class="att-sum-box">No.</div></th>
                    <th class="att-sum-sticky att-sum-c2"><div class="att-sum-box">Employee Name</div></th>
                    <th class="att-sum-sticky att-sum-c3">Department</th>
                    @foreach ($dates as $d)
                        <th class="att-sum-day {{ $d->sunday ? 'is-sunday' : '' }} {{ $d->monthStart ? 'att-sum-month-start' : '' }}" wire:key="hw-{{ $d->date }}">{{ $d->weekday }}</th>
                    @endforeach
                    @foreach ($codes as $code)
                        <th class="att-sum-num {{ $loop->first ? 'att-sum-group' : '' }}" title="{{ $meaning[$code] }}" wire:key="hc-{{ $code }}">{{ $code }}</th>
                    @endforeach
                    <th class="att-sum-num att-sum-group">Total</th>
                    <th class="att-sum-num">Points</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $emp)
                    @php
                        $rowTint = $emp->isFixed ? 'att-sum-fixed' : ($emp->isGy ? 'att-sum-gy' : '');
                        // Day and total cells as one string: per-cell Blade loops add a
                        // Livewire marker to each of the thousands of cells.
                        $cellsHtml = '';
                        foreach ($dates as $d) {
                            $c = $emp->cells[$d->date];
                            $class = 'att-sum-cell'.($c ? ' att-tone att-tone--'.$c->tone : '').($d->sunday ? ' is-sunday' : '').($d->monthStart ? ' att-sum-month-start' : '');
                            $tipAttr = $c ? ' tabindex="0" data-tip="'.e(json_encode($c->tip)).'" aria-label="'.e($c->text.': '.collect($c->tip)->map(fn ($t) => $t[0].' '.$t[1])->join(', ')).'"' : '';
                            $cellsHtml .= '<td class="'.$class.'"'.$tipAttr.'>'.($c ? e($c->text) : '').'</td>';
                        }
                        foreach ($codes as $i => $code) {
                            $n = $emp->totals[$code];
                            $cellsHtml .= '<td class="att-sum-num'.($i === 0 ? ' att-sum-group' : '').($n ? '' : ' att-sum-zero').'">'.$n.'</td>';
                        }
                        $cellsHtml .= '<td class="att-sum-num att-sum-group att-sum-strong'.($emp->totals['total'] ? '' : ' att-sum-zero').'">'.$emp->totals['total'].'</td>';
                        $cellsHtml .= '<td class="att-sum-num att-sum-strong'.($emp->totals['points'] ? '' : ' att-sum-zero').'">'.e($fmtPoints($emp->totals['points'])).'</td>';
                    @endphp
                    <tr wire:key="mis-{{ $emp->idno }}" class="{{ $emp->isResigned ? 'att-sum-resigned' : '' }}">
                        <td class="att-sum-sticky att-sum-c1 {{ $rowTint }}"><div class="att-sum-box">{{ $loop->iteration }}.</div></td>
                        <td class="att-sum-sticky att-sum-c2 {{ $rowTint }}"><div class="att-sum-box">
                            <strong>{{ $emp->lastname }}</strong>, {{ $emp->given }}
                            @if ($emp->isResigned)
                                <span class="att-sum-res">(Resigned{{ $emp->dateresigned ? ': '.date('m/d/Y', strtotime($emp->dateresigned)) : '' }})</span>
                            @endif</div>
                        </td>
                        <td class="att-sum-sticky att-sum-c3 {{ $emp->deptColor ? 'att-sum-dept' : '' }}" @if ($emp->deptColor) style="--dept: {{ $emp->deptColor }}" @endif>{{ $emp->dept_name }}</td>
                        {!! $cellsHtml !!}
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + count($dates) + count($codes) + 2 }}" class="att-empty att-sum-empty">
                            {{ $onlyIncidents || $search !== '' || $onlyAm || $onlyGy ? 'No employees match the search and filters.' : 'No record found!' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="att-tip" :class="below && 'is-below'" x-show="show" x-cloak :style="`left: ${x}px; top: ${y}px`" role="tooltip" wire:ignore>
        <template x-for="(row, i) in tip" :key="i">
            <div class="att-tip-row"><span class="att-tip-label" x-text="row[0]"></span><span class="att-tip-sep">:</span><span class="att-tip-value" x-text="row[1]"></span></div>
        </template>
    </div>
    </div>

</div>
