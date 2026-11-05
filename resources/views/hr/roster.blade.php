@extends('layouts.app')

@section('title', 'HR Roster - Accounting Portal')
@section('page-title', 'HR Roster')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
@php
    $dateFields = \App\Http\Controllers\HrRosterController::DATE_FIELDS;
    $dateKeys = ['date_field', 'from_month', 'from_day', 'to_month', 'to_day'];

    // Roster URL with some query values changed (filter changes go back to page 1)
    $baseCtx = \Illuminate\Support\Arr::except($listContext, ['page']);
    $rosterUrl = fn (array $changes = []) => route('hr.roster', array_filter(
        array_merge($baseCtx, $changes),
        fn ($v) => $v !== null && $v !== '' && $v !== []
    ));

    $fmt = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($d)) : '';

    // Fixed year and day 1 so the month name never overflows (e.g. "February" on the 30th)
    $monthName = fn (int $m, string $f = 'F') => \Carbon\Carbon::create(2000, $m, 1)->format($f);
    $mdText = fn (int $m, ?int $d) => $monthName($m, 'M') . ($d ? ' ' . str_pad($d, 2, '0', STR_PAD_LEFT) : '');

    // Chip: "Mar 01 → Apr 15", or one value when From and To are the same
    $dateChip = null;
    if ($date) {
        $fromText = $mdText($date['from_month'], $date['from_day']);
        $toText   = $mdText($date['to_month'], $date['to_day']);
        $dateChip = $fromText === $toText ? $fromText : "$fromText → $toText";
    }
@endphp

<div class="hr">

    {{-- Header: search bar, filter chips, company tabs (outer), department pills (inner) --}}
    <form method="GET" action="{{ route('hr.roster') }}" id="hrForm" class="hr-head">
        <input type="hidden" name="company" value="{{ $company }}">
        @if ($department)
            <input type="hidden" name="department" value="{{ $department }}">
        @endif

        <div class="hr-head-bar">
            {{-- Searches ID and name only; dates use the filter next to it --}}
            <div class="hr-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="search" value="{{ $search }}" id="hrSearch"
                       placeholder="Search by employee ID or name…" aria-label="Search by employee ID or name"
                       autocomplete="off">
            </div>

            {{-- Date filter: month and day only, any year --}}
            <div class="hr-datebar">
                <select name="date_field" class="hr-select" aria-label="Date to filter">
                    @foreach ($dateFields as $key => $label)
                        <option value="{{ $key }}" @selected(($date['date_field'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                @foreach (['from' => 'From', 'to' => 'To'] as $side => $label)
                    <div class="hr-datebar-group">
                        <span>{{ $label }}:</span>
                        <select name="{{ $side }}_month" class="hr-select" aria-label="{{ $label }} month">
                            <option value="">Month</option>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected(($date[$side . '_month'] ?? null) === $m)>{{ $monthName($m) }}</option>
                            @endforeach
                        </select>
                        <select name="{{ $side }}_day" class="hr-select" aria-label="{{ $label }} day">
                            <option value="">Day</option>
                            @foreach (range(1, 31) as $d)
                                <option value="{{ $d }}" @selected(($date[$side . '_day'] ?? null) === $d)>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <button type="submit" class="hr-btn hr-btn-primary hr-btn-sm">Filter</button>
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

        {{-- Active date filter chip --}}
        @if ($dateChip)
            <div class="hr-chips">
                <span class="hr-chip">
                    <strong>{{ $dateFields[$date['date_field']] }}</strong> {{ $dateChip }}
                    <a href="{{ $rosterUrl(array_fill_keys($dateKeys, null)) }}" aria-label="Remove date filter">×</a>
                </span>
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
                    <th>Job Title</th>
                    <th class="text-center">Eligibility</th>
                    <th class="text-center">Status</th>
                    <th>Date Hired</th>
                    <th>Regularization</th>
                    <th>Fulltime</th>
                    <th>Shift</th>
                    <th class="text-center">Work Setup</th>
                    <th class="text-center">Location</th>
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
