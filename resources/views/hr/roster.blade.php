@extends('layouts.app')

@section('title', 'HR Roster - Accounting Portal')
@section('page-title', 'HR Roster')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
@php
    $filterMeta = [
        'jobtitle'       => ['label' => 'Job Title',      'type' => 'text'],
        'status'         => ['label' => 'Status',         'type' => 'enum', 'options' => \App\Http\Controllers\HrRosterController::STATUSES],
        'dateofhired'    => ['label' => 'Date Hired',     'type' => 'date'],
        'dateofregular'  => ['label' => 'Regularization', 'type' => 'date'],
        'dateoffulltime' => ['label' => 'Fulltime',       'type' => 'date'],
        'shift'          => ['label' => 'Shift',          'type' => 'text'],
        'location'       => ['label' => 'Work Setup',     'type' => 'text'],
        'work_area'      => ['label' => 'Location',       'type' => 'text'],
    ];

    // Roster URL with some query values changed (filter changes go back to page 1)
    $baseCtx = \Illuminate\Support\Arr::except($listContext, ['page']);
    $rosterUrl = fn (array $changes = []) => route('hr.roster', array_filter(
        array_merge($baseCtx, $changes),
        fn ($v) => $v !== null && $v !== '' && $v !== []
    ));
    $withoutFilter = fn (string $key) => $rosterUrl(['f' => \Illuminate\Support\Arr::except($filters, $key)]);

    $fmt = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($d)) : '';

    $chipText = function (string $key, $value) use ($filterMeta) {
        if (!is_array($value)) return $value;
        return match ($value['mode']) {
            'month' => \Carbon\Carbon::create()->month($value['month'])->format('F'),
            'year'  => $value['year'],
            'range' => ($value['from'] ?? '…') . ' → ' . ($value['to'] ?? '…'),
        };
    };
@endphp

<div class="hr">

    {{-- Header: search bar, filter chips, company tabs (outer), department pills (inner) --}}
    <form method="GET" action="{{ route('hr.roster') }}" id="hrForm" class="hr-head">
        <input type="hidden" name="company" value="{{ $company }}">
        @if ($department)
            <input type="hidden" name="department" value="{{ $department }}">
        @endif

        <div class="hr-head-bar">
            {{-- Searches ID and name only; the other columns have their own header filters --}}
            <div class="hr-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="search" value="{{ $search }}" id="hrSearch"
                       placeholder="Search by employee ID or name…" aria-label="Search by employee ID or name"
                       autocomplete="off">
            </div>

            @if ($searching)
                <a href="{{ route('hr.roster', ['company' => $company]) }}" class="hr-btn hr-btn-ghost">
                    <i class="fa-solid fa-xmark"></i> Reset
                </a>
            @endif

            <div class="hr-head-actions">
                <span class="hr-total">
                    <strong>{{ number_format($employees->total()) }}</strong> {{ Str::plural('employee', $employees->total()) }}
                </span>
            </div>
        </div>

        {{-- Active filter chips --}}
        @if ($filters)
            <div class="hr-chips">
                @foreach ($filters as $key => $value)
                    <span class="hr-chip">
                        <strong>{{ $filterMeta[$key]['label'] }}</strong> {{ $chipText($key, $value) }}
                        <a href="{{ $withoutFilter($key) }}" aria-label="Remove {{ $filterMeta[$key]['label'] }} filter">×</a>
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Outer tabs: company --}}
        <div class="hr-tabs" role="tablist" aria-label="Company">
            @foreach ($companyTabs as $tab)
                @php $selected = $company === $tab['code']; @endphp
                <a href="{{ $rosterUrl(['company' => $tab['code'], 'department' => null]) }}"
                   role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}" title="{{ $tab['name'] }}"
                   class="hr-tab {{ $selected ? 'active' : '' }} {{ $searching && $tab['matches'] === 0 ? 'is-dim' : '' }}">
                    {{ $tab['code'] }}
                    <span class="hr-count {{ $searching && $tab['matches'] > 0 ? 'is-match' : '' }}">{{ number_format($tab['matches']) }}</span>
                </a>
            @endforeach
        </div>

        {{-- Inner tabs: department within the selected company --}}
        @if ($departments->isNotEmpty())
            <div class="hr-pills" role="tablist" aria-label="Department">
                <a href="{{ $rosterUrl(['department' => null]) }}"
                   role="tab" aria-selected="{{ $department === null ? 'true' : 'false' }}"
                   class="hr-pill {{ $department === null ? 'active' : '' }}">
                    All
                    <span class="hr-count">{{ number_format($deptTotal) }}</span>
                </a>
                @foreach ($departments as $dept)
                    @php $selected = $department === $dept['id']; @endphp
                    <a href="{{ $rosterUrl(['department' => $dept['id']]) }}"
                       role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}"
                       class="hr-pill {{ $selected ? 'active' : '' }} {{ $searching && $dept['matches'] === 0 ? 'is-dim' : '' }}">
                        {{ $dept['name'] }}
                        <span class="hr-count {{ $searching && $dept['matches'] > 0 ? 'is-match' : '' }}">{{ number_format($dept['matches']) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </form>

    {{-- Table --}}
    <div class="hr-table-wrap" id="hrTableWrap">
        <table class="hr-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Employee Name</th>
                    @foreach (['jobtitle', 'eligibility', 'status', 'dateofhired', 'dateofregular', 'dateoffulltime', 'shift', 'location', 'work_area'] as $colKey)
                        @if ($colKey === 'eligibility')
                            <th class="text-center">Eligibility</th>
                            @continue
                        @endif
                        @php
                            $meta   = $filterMeta[$colKey];
                            $active = isset($filters[$colKey]);
                        @endphp
                        <th class="hr-th-filterable {{ in_array($colKey, ['status', 'location', 'work_area'], true) ? 'text-center' : '' }}">
                            <div class="hr-th-inner">
                                <span>{{ $meta['label'] }}</span>
                                <button type="button" class="hr-th-filter-btn {{ $active ? 'is-active' : '' }}"
                                        data-popover="pop-{{ $colKey }}" title="Filter {{ $meta['label'] }}">
                                    <i class="fa-solid fa-filter"></i>
                                </button>
                            </div>

                            <div class="hr-popover" id="pop-{{ $colKey }}" hidden>
                                @if ($meta['type'] === 'text')
                                    <input type="text" form="hrForm" name="f[{{ $colKey }}]" value="{{ $filters[$colKey] ?? '' }}"
                                           class="hr-popover-input" placeholder="Contains…">

                                @elseif ($meta['type'] === 'enum')
                                    <input type="hidden" form="hrForm" name="f[{{ $colKey }}]" value="{{ $filters[$colKey] ?? '' }}">
                                    <div class="hr-popover-options">
                                        <button type="button" data-value="" class="hr-popover-option {{ empty($filters[$colKey]) ? 'is-active' : '' }}">Any</button>
                                        @foreach ($meta['options'] as $opt)
                                            <button type="button" data-value="{{ $opt }}"
                                                    class="hr-popover-option {{ ($filters[$colKey] ?? '') === $opt ? 'is-active' : '' }}">{{ $opt }}</button>
                                        @endforeach
                                    </div>

                                @elseif ($meta['type'] === 'date')
                                    @php
                                        $dv   = $filters[$colKey] ?? [];
                                        $mode = $dv['mode'] ?? '';
                                    @endphp
                                    <input type="hidden" form="hrForm" name="f[{{ $colKey }}][mode]" value="{{ $mode }}" data-mode-input>
                                    <div class="hr-popover-tabs">
                                        @foreach (['month' => 'Month', 'year' => 'Year', 'range' => 'Range'] as $m => $label)
                                            <button type="button" data-mode="{{ $m }}" class="hr-popover-tab {{ $mode === $m ? 'is-active' : '' }}">{{ $label }}</button>
                                        @endforeach
                                    </div>

                                    <div data-panel="month" @unless ($mode === 'month') hidden @endunless>
                                        <select form="hrForm" name="f[{{ $colKey }}][month]" class="hr-popover-input" @disabled($mode !== 'month')>
                                            <option value="">Pick month…</option>
                                            @foreach (range(1, 12) as $m)
                                                <option value="{{ $m }}" @selected(($dv['month'] ?? null) === $m)>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div data-panel="year" @unless ($mode === 'year') hidden @endunless>
                                        <input type="number" form="hrForm" name="f[{{ $colKey }}][year]" value="{{ $dv['year'] ?? '' }}"
                                               class="hr-popover-input" min="1900" max="2100" placeholder="e.g. {{ now('Asia/Manila')->year }}" @disabled($mode !== 'year')>
                                    </div>
                                    <div data-panel="range" class="hr-popover-range" @unless ($mode === 'range') hidden @endunless>
                                        <input type="date" form="hrForm" name="f[{{ $colKey }}][from]" value="{{ $dv['from'] ?? '' }}" class="hr-popover-input" @disabled($mode !== 'range')>
                                        <span>→</span>
                                        <input type="date" form="hrForm" name="f[{{ $colKey }}][to]" value="{{ $dv['to'] ?? '' }}" class="hr-popover-input" @disabled($mode !== 'range')>
                                    </div>
                                    <p class="hr-popover-hint" data-panel="" @if ($mode !== '') hidden @endif>Pick a mode above.</p>
                                @endif

                                <div class="hr-popover-actions">
                                    <button type="button" class="hr-btn hr-btn-ghost hr-btn-sm" data-clear>Clear</button>
                                    <button type="submit" form="hrForm" class="hr-btn hr-btn-primary hr-btn-sm">Done</button>
                                </div>
                            </div>
                        </th>
                    @endforeach
                    <th>Referred by</th>
                    <th>Referral Fee</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($employees as $e)
                    @php
                        $isRegular = strtoupper((string) $e->status) === 'REGULAR';
                        $locValue  = strtoupper((string) ($e->location ?? ''));
                        $locClass  = match ($locValue) {
                            'WFH'    => 'wfh',
                            'HYBRID' => 'hybrid',
                            default  => 'os',
                        };
                        $givenNames = trim($e->firstname . ' ' . (strtoupper(trim((string) $e->middlename)) === 'N/A' ? '' : $e->middlename));
                        $suffix     = strtoupper(trim((string) $e->suffix)) === 'N/A' ? '' : trim((string) $e->suffix);
                    @endphp
                    <tr class="{{ $e->row_class }}">
                        <td class="hr-id">{{ $e->idno }}</td>
                        {{-- One line: Last name, First Middle Suffix (list is sorted by last name) --}}
                        <td class="hr-name" title="{{ trim($e->lastname . ', ' . $givenNames . ' ' . $suffix) }}">
                            <strong>{{ $e->lastname }}</strong>, {{ $givenNames }}
                            @if ($suffix !== '')
                                <span class="hr-suffix">{{ $suffix }}</span>
                            @endif
                        </td>
                        <td class="hr-wrap" title="{{ $e->jobtitle }}">{{ $e->jobtitle }}</td>
                        <td class="text-center">{{ $e->eligibility_display }}</td>
                        <td class="text-center">
                            <span class="hr-status {{ $isRegular ? 'regular' : 'warning' }}">{{ $e->status }}</span>
                        </td>
                        <td class="hr-date">{{ $fmt($e->dateofhired) }}</td>
                        <td class="hr-date">{{ $fmt($e->dateofregular) }}</td>
                        <td class="hr-date">{{ $fmt($e->dateoffulltime) }}</td>
                        <td>
                            @if ($e->shift_start)
                                <div class="hr-shift">
                                    <span>{{ $e->shift_start }}</span>
                                    <span>{{ $e->shift_end }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="hr-loc {{ $locClass }}">{{ $locValue ?: 'OS' }}</span>
                        </td>
                        <td class="text-center">{{ $e->work_area ?: 'N/A' }}</td>
                        <td>
                            @if ($e->has_referral)
                                <div class="hr-referrer">{{ $e->referrer_name ?: '—' }}</div>
                                <div class="hr-muted hr-small">({{ $e->referredby }})</div>
                            @endif
                        </td>
                        <td class="hr-date">{{ $e->referral_fee }}</td>
                        <td class="text-center">
                            <a href="{{ route('hr.roster.show', ['idno' => $e->idno] + $listContext) }}" class="hr-view-btn">
                                <i class="fa-solid fa-user"></i> View Profile
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="hr-empty">No employees match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Footer: pagination --}}
    <div class="hr-foot">
        {{ $employees->onEachSide(1)->links('hr.partials.pagination') }}
    </div>
</div>
@endsection
