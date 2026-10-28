{{--
    Header shared by the HR trackers: search, year, optional date range, company tabs.
    Expects: $route, $company, $companyTabs, $ctx, $years, $filtering, $total, $unit,
    optional $dates (bool, show the date range), $dateLabel, $hidden (extra query values to keep).
--}}
@php
    $dates  = $dates ?? false;
    $hidden = $hidden ?? [];

    // Same page with some query values changed (always back to page 1)
    $keep = array_merge([
        'search' => $ctx['search'],
        'year'   => $ctx['yearParam'],
        'from'   => $ctx['from'],
        'to'     => $ctx['to'],
    ], $hidden);
    $pageUrl = fn (array $changes = []) => route($route, array_filter(
        array_merge($keep, ['company' => $company], $changes),
        fn ($v) => $v !== null && $v !== ''
    ));
@endphp

<form method="GET" action="{{ route($route) }}" id="hrForm" class="hr-head">
    <input type="hidden" name="company" value="{{ $company }}">
    {{-- per_page has its own select below when $perPageOptions is given --}}
    @foreach (\Illuminate\Support\Arr::except($hidden, isset($perPageOptions) ? ['per_page'] : []) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <div class="hr-head-bar">
        <div class="hr-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="search" value="{{ $ctx['search'] }}" id="hrSearch"
                   placeholder="Search by employee ID or name…" aria-label="Search by employee ID or name"
                   autocomplete="off">
        </div>

        <label class="hr-field">
            <span>Year</span>
            {{-- A date range replaces the year, so picking a year clears it --}}
            <select name="year" class="hr-select" data-autosubmit data-clear-dates @disabled($dates && ($ctx['from'] || $ctx['to']))
                    title="{{ $dates && ($ctx['from'] || $ctx['to']) ? 'Clear the date range to filter by year' : '' }}">
                <option value="ALL" @selected($ctx['yearParam'] === 'ALL')>All years</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected((string) $ctx['yearParam'] === (string) $y)>{{ $y }}</option>
                @endforeach
                @if ($ctx['yearParam'] !== 'ALL' && !$years->contains((int) $ctx['yearParam']))
                    <option value="{{ $ctx['yearParam'] }}" selected>{{ $ctx['yearParam'] }}</option>
                @endif
            </select>
        </label>

        @if ($dates)
            <div class="hr-field">
                <span>{{ $dateLabel ?? 'Effective date' }}</span>
                <div class="hr-range">
                    <input type="date" name="from" value="{{ $ctx['from'] }}" class="hr-select" aria-label="From date">
                    <span class="hr-muted">→</span>
                    <input type="date" name="to" value="{{ $ctx['to'] }}" class="hr-select" aria-label="To date">
                    <button type="submit" class="hr-btn hr-btn-primary hr-btn-sm">Filter</button>
                </div>
            </div>
        @endif

        @if ($filtering)
            <a href="{{ route($route, array_filter(['company' => $company] + $hidden)) }}" class="hr-btn hr-btn-ghost">
                <i class="fa-solid fa-xmark"></i> Reset
            </a>
        @endif

        @isset($perPageOptions)
            <label class="hr-field">
                <span>Show</span>
                <select name="per_page" class="hr-select" data-autosubmit>
                    @foreach ($perPageOptions as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </label>
        @endisset

        <div class="hr-head-actions">
            <span class="hr-total">
                <strong>{{ number_format($total) }}</strong> {{ Str::plural($unit, $total) }}
            </span>
        </div>
    </div>

    <div class="hr-tabs" role="tablist" aria-label="Company">
        @foreach ($companyTabs as $tab)
            @php $selected = $company === $tab['code']; @endphp
            <a href="{{ $pageUrl(['company' => $tab['code']]) }}"
               role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}" title="{{ $tab['name'] }}"
               class="hr-tab {{ $selected ? 'active' : '' }} {{ $ctx['searching'] && $tab['matches'] === 0 ? 'is-dim' : '' }}">
                {{ $tab['code'] }}
                <span class="hr-count {{ $ctx['searching'] && $tab['matches'] > 0 ? 'is-match' : '' }}">{{ number_format($tab['matches']) }}</span>
            </a>
        @endforeach
    </div>
</form>
