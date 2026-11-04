@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endpush
<div class="att-container">
    @section('title', 'Attendance Monitoring')

    {{-- ==================== PAGE HEADER ==================== --}}
    <header class="att-page-head">
        <div>
            <h1 class="att-page-title">Attendance</h1>
            <p class="att-subtitle">Choose who and when, then open a report or apply a bulk tag.</p>
        </div>
    </header>

    {{-- ==================== FILTERS ==================== --}}
    <section class="att-panel" aria-labelledby="att-filters-title">
        <div class="att-panel-header">
            <div>
                <h2 class="att-title" id="att-filters-title">Filters</h2>
                <p class="att-subtitle">Used by every report and by bulk tagging.</p>
            </div>
        </div>

        <div class="att-panel-body att-filters">
            <div class="att-grid">
                {{-- Company --}}
                <div class="att-field">
                    <label class="hr-label" for="att-company">Company</label>
                    <select id="att-company" wire:model.live="company" class="hr-input">
                        <option value="">All companies</option>
                        @foreach ($companies as $c)
                            <option value="{{ $c->companycode }}" wire:key="company-{{ $c->companycode }}">{{ $c->companyname }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Dates --}}
                <div class="att-field">
                    <label class="hr-label" for="att-start">Start date</label>
                    <input id="att-start" type="date" wire:model.live="startDate" class="hr-input @error('startDate') is-invalid @enderror">
                    @error('startDate') <span class="hr-error">{{ $message }}</span> @enderror
                </div>
                <div class="att-field">
                    <label class="hr-label" for="att-end">End date</label>
                    <input id="att-end" type="date" wire:model.live="endDate" min="{{ $startDate }}"
                           class="hr-input @error('endDate') is-invalid @enderror @if (! $datesValid && $startDate && $endDate) is-invalid @endif">
                    @error('endDate')
                        <span class="hr-error">{{ $message }}</span>
                    @else
                        @if (! $datesValid && $startDate && $endDate)
                            <span class="hr-error">End date must be on or after the start date.</span>
                        @endif
                    @enderror
                </div>
            </div>

            <div class="att-presets" role="group" aria-label="Quick date ranges">
                <span class="hr-label">Quick range</span>
                <div class="hr-segmented">
                    @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'this-week' => 'This week', 'this-month' => 'This month', 'last-month' => 'Last month'] as $key => $label)
                        <button type="button" class="hr-segmented-item" wire:key="preset-{{ $key }}" wire:click="setRange('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Departments --}}
            <div class="att-field" x-data="{ q: '' }">
                <div class="att-dept-head">
                    <div class="att-dept-title">
                        <span class="hr-label">Departments</span>
                        <span class="hr-badge {{ count($departments) ? 'hr-badge-info' : 'hr-badge-neutral' }}">
                            {{ count($departments) }} of {{ $departmentList->count() }} selected
                        </span>
                    </div>
                    <div class="att-dept-tools">
                        <input type="search" x-model="q" class="hr-input att-dept-search" placeholder="Search departments" aria-label="Search departments">
                        <button type="button" class="hr-btn hr-btn-sm" wire:click="selectAllDepartments">Select all</button>
                        <button type="button" class="hr-btn hr-btn-sm hr-btn-ghost" wire:click="clearDepartments" @disabled(empty($departments))>Clear</button>
                    </div>
                </div>

                <div class="hr-tile att-checkbox-grid @error('departments') is-invalid @enderror">
                    @forelse ($departmentList as $dept)
                        <label class="att-checkbox-label" wire:key="dept-{{ $dept->id }}"
                               data-name="{{ strtolower($dept->department) }}"
                               x-show="! q || $el.dataset.name.includes(q.toLowerCase())">
                            <input type="checkbox" wire:model.live="departments" value="{{ $dept->id }}" class="att-checkbox">
                            <span>{{ $dept->department }}</span>
                        </label>
                    @empty
                        <p class="att-empty-note">No departments with active employees.</p>
                    @endforelse
                </div>
                @error('departments') <span class="hr-error">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Current selection, so the actions below are never a guess --}}
        <div class="att-scope">
            <span class="hr-label">Applies to</span>
            <div class="hr-chips">
                <span class="hr-chip"><strong>{{ $companyName ?? 'All companies' }}</strong></span>
                <span class="hr-chip">
                    @if ($datesValid) <strong>{{ $rangeLabel }}</strong> @else <span class="att-text-danger">Invalid date range</span> @endif
                </span>
                <span class="hr-chip">
                    @if ($selectedDeptNames->isEmpty())
                        <strong>All departments</strong>&nbsp;(reports only)
                    @elseif ($selectedDeptNames->count() <= 3)
                        <strong>{{ $selectedDeptNames->join(', ') }}</strong>
                    @else
                        <strong>{{ $selectedDeptNames->take(2)->join(', ') }}</strong>&nbsp;+{{ $selectedDeptNames->count() - 2 }} more
                    @endif
                </span>
            </div>
        </div>
    </section>

    {{-- ==================== ACTIONS ==================== --}}
    <div class="att-actions-grid">

        {{-- Reports --}}
        <section class="att-panel att-action-card" aria-labelledby="att-reports-title">
            <div>
                <h3 class="att-action-title" id="att-reports-title">
                    <svg width="18" height="18" class="text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reports
                </h3>
                <p class="att-action-desc">Opens in a new tab using the filters above.</p>
            </div>

            <div class="att-btn-list">
                @if ($datesValid)
                    <a href="{{ route('attendance.employee-logs', [
                            'company' => $company,
                            'startdate' => $startDate,
                            'enddate' => $endDate,
                            'departments' => $departments,
                        ]) }}"
                       target="_blank" rel="noopener" class="att-btn-item">
                        <span class="att-btn-text">
                            <span>Employee Logs</span>
                            <small>{{ $logsTooLong ? 'Shows the first '.\App\Livewire\Attendance\EmployeeLogs::MAX_DAYS.' days of this range' : 'Employee attendance records and infraction tagging' }}</small>
                        </span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @else
                    <span class="att-btn-item is-disabled" aria-disabled="true">
                        <span class="att-btn-text">
                            <span>Employee Logs</span>
                            <small>Fix the date range to open this report</small>
                        </span>
                    </span>
                @endif

                @if ($datesValid)
                    <a href="{{ route('attendance.summary', [
                            'company' => $company,
                            'startdate' => $startDate,
                            'enddate' => $endDate,
                            'departments' => $departments,
                        ]) }}"
                       target="_blank" rel="noopener" class="att-btn-item">
                        <span class="att-btn-text">
                            <span>Attendance Summary (Absences / Late)</span>
                            <small>{{ $logsTooLong ? 'Shows the first '.\App\Livewire\Attendance\AttendanceSummary::MAX_DAYS.' days of this range' : 'Summary of attendance records' }}</small>
                        </span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @else
                    <span class="att-btn-item is-disabled" aria-disabled="true">
                        <span class="att-btn-text">
                            <span>Attendance Summary (Absences / Late)</span>
                            <small>Fix the date range to open this report</small>
                        </span>
                    </span>
                @endif

                @foreach ([
                    ['attendance.missed-summary', 'Missed Logs / OB Summary', 'Summary of lates and overbreaks tagging'],
                    ['attendance.break-monitoring', 'Break Monitoring', 'Over-breaks and missing lunch logs to review'],
                ] as [$route, $label, $hint])
                    @if ($datesValid)
                        <a href="{{ route($route, [
                                'company' => $company,
                                'startdate' => $startDate,
                                'enddate' => $endDate,
                                'departments' => $departments,
                            ]) }}"
                           target="_blank" rel="noopener" class="att-btn-item" wire:key="report-{{ $route }}">
                            <span class="att-btn-text">
                                <span>{{ $label }}</span>
                                <small>{{ $logsTooLong ? 'Shows the first '.\App\Livewire\Attendance\EmployeeLogs::MAX_DAYS.' days of this range' : $hint }}</small>
                            </span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @else
                        <span class="att-btn-item is-disabled" aria-disabled="true" wire:key="report-{{ $route }}">
                            <span class="att-btn-text">
                                <span>{{ $label }}</span>
                                <small>Fix the date range to open this report</small>
                            </span>
                        </span>
                    @endif
                @endforeach
            </div>
        </section>

        {{-- Bulk Tagging --}}
        <section class="att-panel att-action-card" aria-labelledby="att-tag-title">
            <div>
                <h3 class="att-action-title" id="att-tag-title">
                    <svg width="18" height="18" class="text-emerald" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    Bulk Tagging
                </h3>
                <p class="att-action-desc">Tag every active employee in the selected departments for the date range. You'll see what changes before anything is saved.</p>
            </div>

            <form wire:submit="reviewTagging" class="att-tag-form">
                <fieldset class="att-tag-options">
                    <legend class="hr-label">Tag</legend>
                    @foreach ($tags as $code => $label)
                        <label class="att-tag-option" wire:key="tag-{{ $code }}">
                            <input type="radio" name="tag" value="{{ $code }}" wire:model="tag">
                            <span class="att-tag-option__body">
                                <span class="att-tag-option__name">{{ $label }} <span class="att-tag-code">{{ $code }}</span></span>
                            </span>
                        </label>
                    @endforeach
                    @error('tag') <span class="hr-error">{{ $message }}</span> @enderror
                </fieldset>

                <p class="hr-hint att-tag-note">
                    Leave days and records with {{ implode(', ', $protectedRemarks) }} remarks are never changed.
                    Replaced remarks are kept as previous remarks.
                </p>

                <button type="submit" class="hr-btn hr-btn-primary att-btn-primary" wire:loading.attr="disabled" wire:target="reviewTagging">
                    <span wire:loading.remove wire:target="reviewTagging">Review changes</span>
                    <span wire:loading wire:target="reviewTagging">Checking records…</span>
                </button>
            </form>
        </section>
    </div>

    {{-- ==================== CONFIRM BULK TAG ==================== --}}
    @if ($tagPreview)
        @php $changes = $tagPreview['update'] + $tagPreview['insert']; @endphp
        <div class="att-modal-backdrop" wire:click.self="cancelTagging" x-data x-on:keydown.escape.window="$wire.cancelTagging()">
            <div class="att-modal" role="dialog" aria-modal="true" aria-labelledby="att-confirm-title">
                <header class="att-modal__head">
                    <div>
                        <h3 id="att-confirm-title">Apply {{ $tags[$tag] ?? $tag }}?</h3>
                        <span class="att-subtitle">
                            {{ $companyName ?? 'All companies' }} · {{ $rangeLabel }} ·
                            {{ $selectedDeptNames->count() }} {{ str('department')->plural($selectedDeptNames->count()) }}
                        </span>
                    </div>
                    <button type="button" class="hr-icon-btn" wire:click="cancelTagging" aria-label="Close">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="att-modal__body">
                    <p class="att-modal__lead">
                        <strong>{{ number_format($tagPreview['employees']) }}</strong> active {{ str('employee')->plural($tagPreview['employees']) }}
                        across <strong>{{ $tagPreview['days'] }}</strong> {{ str('day')->plural($tagPreview['days']) }}.
                    </p>

                    <dl class="att-stats">
                        <div class="hr-tile att-stat">
                            <dt>Will update</dt>
                            <dd>{{ number_format($tagPreview['update']) }}</dd>
                        </div>
                        <div class="hr-tile att-stat">
                            <dt>Will add</dt>
                            <dd>{{ number_format($tagPreview['insert']) }}</dd>
                        </div>
                        <div class="hr-tile att-stat">
                            <dt>Protected</dt>
                            <dd>{{ number_format($tagPreview['protected']) }}</dd>
                        </div>
                        <div class="hr-tile att-stat">
                            <dt>Already tagged</dt>
                            <dd>{{ number_format($tagPreview['unchanged']) }}</dd>
                        </div>
                    </dl>

                    <p class="hr-hint">Protected: leave days and {{ implode(', ', $protectedRemarks) }} remarks, which are left as they are.</p>

                    @if ($changes === 0)
                        <p class="hr-hint">Nothing to change for this selection.</p>
                    @else
                        <p class="hr-warn">This writes {{ number_format($changes) }} attendance {{ str('record')->plural($changes) }} and can't be undone in one step.</p>
                    @endif
                </div>

                <footer class="att-modal__foot">
                    <button type="button" class="hr-btn hr-btn-ghost" wire:click="cancelTagging">Cancel</button>
                    <button type="button" class="hr-btn hr-btn-primary" wire:click="applyTagging"
                            wire:loading.attr="disabled" wire:target="applyTagging" @disabled($changes === 0)>
                        <span wire:loading.remove wire:target="applyTagging">Apply tag</span>
                        <span wire:loading wire:target="applyTagging">Applying…</span>
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- ==================== TOAST ==================== --}}
    <div x-data="{ show: false, msg: '' }"
         x-on:notify.window="msg = $event.detail.message; show = true; clearTimeout(window.__attToast); window.__attToast = setTimeout(() => show = false, 4000)"
         x-show="show"
         x-transition
         x-cloak
         class="hr-toast"
         role="status">
        <span x-text="msg"></span>
    </div>
</div>
