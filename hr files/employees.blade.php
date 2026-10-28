@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employees.css') }}">
@endpush

<div>
    @section('title', 'MANAGE EMPLOYEES')

    @php
        $filterMeta = [
            'birthdate'      => ['label' => 'Birthdate', 'type' => 'date'],
            'jobtitle'       => ['label' => 'Job Title', 'type' => 'text'],
            'status'         => ['label' => 'Status',    'type' => 'enum', 'options' => ['REGULAR', 'PROBATIONARY']],
            'dateofhired'    => ['label' => 'Hired',     'type' => 'date'],
            'dateofregular'  => ['label' => 'Regular',   'type' => 'date'],
            'dateoffulltime' => ['label' => 'Fulltime',  'type' => 'date'],
            'shift_display'  => ['label' => 'Shift',     'type' => 'text'],
            'setup'          => ['label' => 'Setup',     'type' => 'setup'],
        ];

        $isActive = function (string $key) use ($filters) {
            if (!isset($filters[$key])) return false;
            $v = $filters[$key];
            if (!is_array($v)) return $v !== '' && $v !== null;

            if ($key === 'setup') {
                return ($v['work_area'] ?? '') !== '' || ($v['location'] ?? '') !== '';
            }

            $mode = $v['mode'] ?? '';
            if ($mode === 'month') return ($v['month'] ?? '') !== '';
            if ($mode === 'year')  return ($v['year']  ?? '') !== '';
            if ($mode === 'range') return ($v['from']  ?? '') !== '' || ($v['to'] ?? '') !== '';
            return false;
        };

        $searching = $this->hasActiveSearch;
    @endphp

    <div class="emp-page">

        {{-- Header: search bar, filter chips, company tabs (outer), department pills (inner) --}}
        <section class="emp-head hr-card">
        <div class="emp-head-bar">
            {{-- Searches ID and name only; the other columns have their own header filters --}}
            <div class="emp-search">
                <svg class="emp-search-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="search" wire:model.live.debounce.300ms="search"
                       class="hr-input" placeholder="Search by employee ID or name…" aria-label="Search by employee ID or name">
            </div>

            @if ($searching)
                <button type="button" wire:click="clearFilters" class="hr-btn hr-btn-ghost">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reset
                </button>
            @endif

            <div class="emp-head-actions">
                <span class="emp-total" wire:loading.remove wire:target="search, filters, onlyFixedRate, onlyGraveyard, setCompany, setDepartment, clearFilters, removeFilter">
                    <strong>{{ number_format($employees->total()) }}</strong> {{ Str::plural('employee', $employees->total()) }}
                </span>
                <span class="emp-total" wire:loading wire:target="search, filters, onlyFixedRate, onlyGraveyard, setCompany, setDepartment, clearFilters, removeFilter">Loading…</span>

                <button type="button"
                        wire:click="$dispatch('openPanel', { component: 'employees.add-employee' })"
                        class="hr-btn hr-btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    New Employee
                </button>
            </div>
        </div>

        {{-- Active filter chips --}}
        @if ($this->activeFilterCount > 0)
            <div class="emp-head-chips emp-chips">
                @foreach ($filters as $key => $value)
                    @php $meta = $filterMeta[$key] ?? null; @endphp
                    @if (!$meta) @continue @endif

                    @if ($key === 'setup' && is_array($value))
                        @if (($value['work_area'] ?? '') !== '' || ($value['location'] ?? '') !== '')
                            <span class="emp-chip">
                                @if (($value['work_area'] ?? '') !== '')
                                    Loc: {{ $value['work_area'] }}
                                @endif
                                @if (($value['work_area'] ?? '') !== '' && ($value['location'] ?? '') !== '')
                                    ·
                                @endif
                                @if (($value['location'] ?? '') !== '')
                                    Setup: {{ $value['location'] }}
                                @endif
                                <button type="button" wire:click="removeFilter('setup')">×</button>
                            </span>
                        @endif
                    @elseif (is_array($value))
                        @php $mode = $value['mode'] ?? ''; @endphp
                        @if ($mode === 'month' && ($value['month'] ?? '') !== '')
                            <span class="emp-chip">
                                <strong>{{ $meta['label'] }}</strong>
                                {{ \Carbon\Carbon::create()->month((int) $value['month'])->format('F') }}
                                <button type="button" wire:click="removeFilter('{{ $key }}')">×</button>
                            </span>
                        @elseif ($mode === 'year' && ($value['year'] ?? '') !== '')
                            <span class="emp-chip">
                                <strong>{{ $meta['label'] }}</strong>
                                {{ $value['year'] }}
                                <button type="button" wire:click="removeFilter('{{ $key }}')">×</button>
                            </span>
                        @elseif ($mode === 'range' && (($value['from'] ?? '') !== '' || ($value['to'] ?? '') !== ''))
                            <span class="emp-chip">
                                <strong>{{ $meta['label'] }}</strong>
                                {{ ($value['from'] ?? '') !== '' ? $value['from'] : '…' }}
                                →
                                {{ ($value['to'] ?? '') !== '' ? $value['to'] : '…' }}
                                <button type="button" wire:click="removeFilter('{{ $key }}')">×</button>
                            </span>
                        @endif
                    @elseif ($value !== '')
                        <span class="emp-chip">
                            <strong>{{ $meta['label'] }}</strong>
                            {{ $value }}
                            <button type="button" wire:click="removeFilter('{{ $key }}')">×</button>
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Outer tabs: company --}}
        <div class="emp-tabs" role="tablist" aria-label="Company">
            @foreach ($companyTabs as $tab)
                @php
                    $matches  = (int) ($tab['matches'] ?? 0);
                    $selected = $company === $tab['name'];
                @endphp
                <button type="button"
                        wire:key="company-tab-{{ $loop->index }}"
                        wire:click="setCompany('{{ $tab['name'] }}')"
                        role="tab"
                        aria-selected="{{ $selected ? 'true' : 'false' }}"
                        class="emp-tab {{ $selected ? 'active' : '' }} {{ $searching && $matches === 0 ? 'is-dim' : '' }}">
                    {{ $tab['name'] }}
                    <span class="emp-count {{ $searching && $matches > 0 ? 'is-match' : '' }}">{{ number_format($matches) }}</span>
                </button>
            @endforeach
        </div>

        {{-- Inner tabs: department within the selected company --}}
        @if (!empty($departments))
            <div class="emp-subnav">
                <div class="emp-pills" role="tablist" aria-label="Department">
                    @foreach ($departments as $dept)
                        @php
                            $matches  = (int) ($dept['matches'] ?? 0);
                            $selected = $department === $dept['name'];
                        @endphp
                        <button type="button"
                                wire:key="dept-pill-{{ $dept['id'] }}"
                                wire:click="setDepartment('{{ $dept['name'] }}')"
                                role="tab"
                                aria-selected="{{ $selected ? 'true' : 'false' }}"
                                class="emp-pill {{ $selected ? 'active' : '' }} {{ $searching && $matches === 0 ? 'is-dim' : '' }}">
                            {{ $dept['name'] }}
                            <span class="emp-count {{ $searching && $matches > 0 ? 'is-match' : '' }}">{{ number_format($matches) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
        </section>

        {{-- Table --}}
        <div class="emp-table-wrapper" @scroll.passive="$dispatch('emp-table-scroll')">
            <table class="emp-table">
                {{-- Widths live in employees.css --}}
                <colgroup>
                    @for ($c = 0; $c < 13; $c++) <col> @endfor
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th>Emp ID</th>
                        <th>Name</th>

                        @foreach (['birthdate','jobtitle','status','dateofhired','dateofregular','dateoffulltime','shift_display','setup'] as $colKey)
                            @php
                                $meta   = $filterMeta[$colKey];
                                $active = $isActive($colKey);
                            @endphp
                            <th class="emp-th-filterable {{ in_array($colKey, ['status', 'setup'], true) ? 'text-center' : '' }}"
                                wire:key="th-{{ $colKey }}">
                                <div class="emp-th-inner">
                                    <span class="emp-th-label">{{ $meta['label'] }}</span>
                                    <button type="button"
                                            class="emp-th-filter-btn {{ $active ? 'is-active' : '' }}"
                                            wire:click.stop="openFilterPopover('{{ $colKey }}')"
                                            title="Filter {{ $meta['label'] }}">
                                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M3 4.5h18M6 12h12M10 19.5h4"/>
                                        </svg>
                                    </button>
                                </div>

                                @if ($openFilter === $colKey)
                                    <div class="emp-th-popover"
                                         wire:key="popover-{{ $colKey }}"
                                         wire:ignore.self
                                         x-data="thPopover"
                                         @scroll.window.passive="place()"
                                         @resize.window="place()"
                                         @emp-table-scroll.window="place()"
                                         @keydown.escape.window="$wire.closeFilterPopover()">
                                        @if ($meta['type'] === 'text')
                                            <input type="text"
                                                   class="emp-popover-input"
                                                   wire:model.live.debounce.350ms="filters.{{ $colKey }}"
                                                   placeholder="Contains…"
                                                   autofocus>

                                        @elseif ($meta['type'] === 'enum')
                                            <div class="emp-popover-options">
                                                <button type="button"
                                                        class="emp-popover-option {{ empty($filters[$colKey] ?? '') ? 'is-active' : '' }}"
                                                        wire:click="$set('filters.{{ $colKey }}', '')">
                                                    Any
                                                </button>
                                                @foreach ($meta['options'] as $opt)
                                                    <button type="button"
                                                            class="emp-popover-option {{ ($filters[$colKey] ?? '') === $opt ? 'is-active' : '' }}"
                                                            wire:click="$set('filters.{{ $colKey }}', '{{ $opt }}')">
                                                        {{ $opt }}
                                                    </button>
                                                @endforeach
                                            </div>

                                        @elseif ($meta['type'] === 'setup')
                                            <label class="emp-popover-label">Location</label>
                                            <input type="text"
                                                   class="emp-popover-input"
                                                   wire:model.live.debounce.350ms="filters.setup.work_area"
                                                   placeholder="Contains…">
                                            <label class="emp-popover-label">Work Setup</label>
                                            <input type="text"
                                                   class="emp-popover-input"
                                                   wire:model.live.debounce.350ms="filters.setup.location"
                                                   placeholder="Contains…">

                                        @elseif ($meta['type'] === 'date')
                                            @php
                                                $dv = is_array($filters[$colKey] ?? null) ? $filters[$colKey] : [];
                                                $currentMode = $dv['mode'] ?? '';
                                            @endphp
                                            <div class="emp-popover-tabs">
                                                <button type="button"
                                                        class="emp-popover-tab {{ $currentMode === 'month' ? 'is-active' : '' }}"
                                                        wire:click="setDateMode('{{ $colKey }}', 'month')">
                                                    Month
                                                </button>
                                                <button type="button"
                                                        class="emp-popover-tab {{ $currentMode === 'year' ? 'is-active' : '' }}"
                                                        wire:click="setDateMode('{{ $colKey }}', 'year')">
                                                    Year
                                                </button>
                                                <button type="button"
                                                        class="emp-popover-tab {{ $currentMode === 'range' ? 'is-active' : '' }}"
                                                        wire:click="setDateMode('{{ $colKey }}', 'range')">
                                                    Range
                                                </button>
                                            </div>

                                            @if ($currentMode === 'month')
                                                <select class="emp-popover-input" wire:model.live="filters.{{ $colKey }}.month">
                                                    <option value="">Pick month…</option>
                                                    @foreach (range(1, 12) as $m)
                                                        <option value="{{ $m }}">
                                                            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @elseif ($currentMode === 'year')
                                                <input type="number"
                                                       class="emp-popover-input"
                                                       min="1900" max="2100"
                                                       placeholder="e.g. 2026"
                                                       wire:model.live.debounce.350ms="filters.{{ $colKey }}.year">
                                            @elseif ($currentMode === 'range')
                                                <div class="emp-popover-range">
                                                    <input type="date"
                                                           class="emp-popover-input"
                                                           wire:model.live="filters.{{ $colKey }}.from">
                                                    <span class="emp-popover-range-sep">→</span>
                                                    <input type="date"
                                                           class="emp-popover-input"
                                                           wire:model.live="filters.{{ $colKey }}.to">
                                                </div>
                                            @else
                                                <p class="emp-popover-hint">Pick a mode above.</p>
                                            @endif
                                        @endif

                                        <div class="emp-popover-actions">
                                            <button type="button"
                                                    class="hr-btn hr-btn-ghost hr-btn-sm"
                                                    wire:click="removeFilter('{{ $colKey }}')">
                                                Clear
                                            </button>
                                            <button type="button"
                                                    class="hr-btn hr-btn-primary hr-btn-sm"
                                                    wire:click="closeFilterPopover()">
                                                Done
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </th>
                        @endforeach

                        <th class="text-center emp-col-action">Action</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($employees as $k => $e)
                        @php
                            $statusKey   = strtoupper((string) $e->status);
                            $statusClass = $statusKey === 'REGULAR' ? 'regular' : 'warning';

                            $hasShift   = $e->startshift && $e->endshift;
                            $shiftStart = $hasShift ? date('h:i A', strtotime($e->startshift)) : '';
                            $shiftEnd   = $hasShift ? date('h:i A', strtotime($e->endshift)) : '';

                            $fmt = fn ($d) => $d ? date('M d, Y', strtotime($d)) : '';

                            $setup    = $e->work_area ?: 'N/A';
                            $locValue = strtoupper((string) ($e->location ?? ''));
                            $locClass = match ($locValue) {
                                'WFH'    => 'wfh',
                                'HYBRID' => 'hybrid',
                                default  => 'os',
                            };

                            $isOpen = ($expandedId === (int) $e->profile_id);

                            $salaryType = (string) ($e->salary_type ?? '');
                            $isBlue = in_array($salaryType, ['Fixed', 'Daily'], true);

                            $isGY = \App\Helpers\ShiftHelper::isGyShift($e->startshift);

                            $rowClass = '';
                            if ($isBlue)       $rowClass = 'emp-row-blue';
                            elseif ($isGY)     $rowClass = 'emp-row-gray';
                        @endphp
                        <tr wire:key="emp-{{ $e->profile_id }}" class="{{ $rowClass }}">
                            <td class="text-center row-number">
                                {{ ($employees->currentPage() - 1) * $employees->perPage() + $k + 1 }}.
                            </td>
                            <td class="emp-idno">{{ $e->idno }}</td>
                            @php
                                $givenNames = trim($e->firstname . ' ' . ($e->middlename ?? ''));
                                $fullName   = trim($e->lastname . ', ' . $givenNames . (!empty($e->suffix) ? ' ' . $e->suffix : ''));
                            @endphp
                            {{-- One line: Last name, First Middle Suffix (list is sorted by last name) --}}
                            <td class="emp-name" title="{{ $fullName }}">
                                <strong>{{ $e->lastname }}</strong>, {{ $givenNames }}
                                @if (!empty($e->suffix))
                                    <span class="suffix">{{ $e->suffix }}</span>
                                @endif
                            </td>
                            <td class="emp-date">{{ $fmt($e->birthdate) }}</td>
                            <td class="emp-wrap" title="{{ $e->jobtitle }}">{{ $e->jobtitle }}</td>
                            <td class="text-center">
                                <span class="emp-status {{ $statusClass }}">{{ $e->status }}</span>
                            </td>
                            <td class="emp-date">{{ $fmt($e->dateofhired) }}</td>
                            <td class="emp-date">{{ $fmt($e->dateofregular) }}</td>
                            <td class="emp-date">{{ $fmt($e->dateoffulltime) }}</td>
                            <td>
                                @if ($hasShift)
                                    <div class="emp-shift">
                                        <span>{{ $shiftStart }}</span>
                                        <span>{{ $shiftEnd }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="emp-setup">
                                    <span class="setup">{{ $setup }}</span>
                                    <span class="emp-loc {{ $locClass }}">{{ $locValue ?: 'OS' }}</span>
                                </div>
                            </td>

                            <td class="emp-action">
                                <div x-data="actionMenu()" class="emp-action-menu">
                                    <button type="button"
                                            @click="toggle($event)"
                                            :class="{ 'is-open': open }"
                                            class="btn-emp-menu"
                                            title="Actions">
                                        ⋯
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="open"
                                            x-cloak
                                            @click.outside="open = false"
                                            @keydown.escape.window="open = false"
                                            @scroll.window.passive="open = false"
                                            :style="`position:fixed; top:${top}px; left:${left}px; z-index:9999;`"
                                            class="emp-action-dropdown">
                                            <button type="button"
                                                    onclick="window.location.href='{{ route('employees.profile', ['id' => $e->profile_id] + $this->listContext) }}'"
                                                    @click="open = false"
                                                    class="emp-action-item">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0v.75H4.5v-.75z"/>
                                                </svg>
                                                View Profile
                                            </button>
                                            <button type="button"
                                                    wire:click="$dispatch('openPanel', { component: 'employees.edit-employee', arguments: { profileId: {{ $e->profile_id }} } })"
                                                    @click="open = false"
                                                    class="emp-action-item">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                                                </svg>
                                                Edit Employee
                                            </button>
                                            <div class="emp-action-separator"></div>
                                            <button type="button"
                                                    onclick="window.location.href='{{ route('employee.movement', ['idno' => $e->idno]) }}'"
                                                    @click="open = false"
                                                    class="emp-action-item">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                                                </svg>
                                                Employee Movement
                                            </button>
                                            <button type="button"
                                                    wire:click="$dispatch('openPanel', { component: 'employees.apply-leave', arguments: { profileId: {{ $e->profile_id }} } })"
                                                    @click="open = false"
                                                    class="emp-action-item">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                                                </svg>
                                                Apply Leave
                                            </button>
                                            <button type="button"
                                                    wire:click="$dispatch('openPanel', { component: 'employees.apply-eeo', arguments: { profileId: {{ $e->profile_id }} } })"
                                                    @click="open = false"
                                                    class="emp-action-item">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Apply EEO
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </td>

                            <td class="text-center emp-toggle">
                                <button type="button"
                                        wire:click="toggleDetail({{ $e->profile_id }})"
                                        class="btn-emp-detail {{ $isOpen ? 'is-open' : '' }}"
                                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                        title="{{ $isOpen ? 'Hide' : 'Show' }} record history">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        @if ($isOpen)
                            <tr class="emp-detail-row {{ $rowClass }}" wire:key="detail-{{ $e->profile_id }}">
                                <td colspan="13">
                                    <div class="emp-detail-panel">
                                        <div class="emp-detail-section">
                                            <span class="emp-detail-label">Added by</span>
                                            <span class="emp-detail-value">
                                                {{ $e->addedby ?: 'System' }}
                                                @if ($e->addeddatetime)
                                                    <span class="emp-detail-time">{{ date('M d, Y h:i A', strtotime($e->addeddatetime)) }}</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="emp-detail-section">
                                            <span class="emp-detail-label">Last updated by</span>
                                            <span class="emp-detail-value">
                                                {{ $e->updatedby ?: 'Never updated' }}
                                                @if ($e->updateddatetime)
                                                    <span class="emp-detail-time">{{ date('M d, Y h:i A', strtotime($e->updateddatetime)) }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="13" class="emp-empty">
                                <div>No employees match your filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer: row colour legend + pagination --}}
        <div class="emp-foot">
            <div class="emp-legend">
                {{-- Checking a legend item shows only those rows --}}
                <label class="emp-legend-item">
                    <input type="checkbox" wire:model.live="onlyFixedRate">
                    <span class="emp-legend-swatch blue"></span> Fixed / daily rate
                </label>
                <label class="emp-legend-item">
                    <input type="checkbox" wire:model.live="onlyGraveyard">
                    <span class="emp-legend-swatch gray"></span> Graveyard shift
                </label>
            </div>
            <div class="emp-pagination">
                {{ $employees->links() }}
            </div>
        </div>
    </div>

    {{-- Register the Alpine component. @script only runs once after Alpine boots. --}}
    @script
    <script>
        Alpine.data('actionMenu', () => ({
            open: false,
            top: 0,
            left: 0,

            toggle(event) {
                const btn = event.currentTarget;
                const rect = btn.getBoundingClientRect();

                this.top  = rect.bottom + 4;
                this.left = Math.max(8, rect.right - 180);

                this.open = !this.open;
            }
        }));

        // Column filter popover: fixed to its header cell so the
        // horizontally scrolling table wrapper does not clip it.
        Alpine.data('thPopover', () => ({
            init() {
                this.$nextTick(() => this.place());
            },

            place() {
                const th = this.$el.closest('th');
                if (!th) return;

                const rect  = th.getBoundingClientRect();
                const width = this.$el.offsetWidth;
                const left  = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);

                this.$el.style.top  = `${rect.bottom + 6}px`;
                this.$el.style.left = `${left}px`;
            }
        }));
    </script>
    @endscript
</div>