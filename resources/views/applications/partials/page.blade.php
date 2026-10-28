{{--
    Leave-page layout (leaves.css / lv-tabs.js) for the HSO and site transfer applications.
    Expects: $route, $noun, $dateLabel, $headers (label => th class), $dataId, $ackUrl,
    optional $showUrl, plus the controller's $years, $year, $from, $to, $rangeFrom, $rangeTo, $tabs, $total.
--}}
<div class="lv ap"
     data-ack-url="{{ $ackUrl }}"
     @isset($showUrl) data-show-url="{{ $showUrl }}" @endisset
     data-csrf="{{ csrf_token() }}">

    {{-- ===================== Filters ===================== --}}
    <div class="lv-toolbar">
        <form method="GET" action="{{ route($route) }}" class="lv-filters">
            <div class="lv-field">
                <label for="year">Year</label>
                <select name="year" id="year" onchange="this.form.from.value=''; this.form.to.value=''; this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}{{ $y === (int) now('Asia/Manila')->year ? ' (This year)' : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lv-field">
                <label for="from">{{ $dateLabel }} from</label>
                <input type="date" name="from" id="from" value="{{ $from }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31">
            </div>

            <div class="lv-field">
                <label for="to">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31">
            </div>

            <div class="lv-actions">
                <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="{{ route($route, ['year' => $year]) }}">Reset</a>
            </div>
        </form>

        <div class="lv-search" role="search">
            <label for="lvSearch">Search</label>
            <div class="lv-search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="lvSearch" placeholder="Name or ID..." autocomplete="off">
                <span class="lv-search-count" id="lvSearchCount" aria-live="polite"></span>
                <button type="button" id="lvPrev" title="Previous match (Shift+Enter)" aria-label="Previous match" disabled><i class="fa-solid fa-chevron-up"></i></button>
                <button type="button" id="lvNext" title="Next match (Enter)" aria-label="Next match" disabled><i class="fa-solid fa-chevron-down"></i></button>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="lv-alert" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}
        </div>
    @endif

    <div class="lv-range">
        <span>
            <i class="fa-regular fa-calendar"></i>
            {{ date('M d, Y', strtotime($rangeFrom)) }} – {{ date('M d, Y', strtotime($rangeTo)) }}
        </span>
        <span><strong>{{ number_format($total) }}</strong> {{ Str::plural($noun, $total) }}</span>
        <span class="ap-legend">
            <span class="ap-swatch ap-swatch--due"></span> Awaiting Accounting
            <span class="ap-swatch ap-swatch--acked"></span> Acknowledged
        </span>
    </div>

    {{-- ===================== Company tabs ===================== --}}
    <div class="lv-tabs lv-company-tabs" role="tablist" aria-label="Companies">
        @foreach ($tabs as $i => $company)
            <button type="button"
                    class="lv-tab {{ $i === 0 ? 'is-active' : '' }}"
                    role="tab"
                    aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                    data-company="{{ $company['code'] }}"
                    title="{{ $company['name'] }}">
                {{ $company['code'] }}
                <span class="lv-count">{{ number_format($company['count']) }}</span>
                <span class="ap-due" title="Awaiting Accounting acknowledgement" @if (!$company['due']) hidden @endif>{{ $company['due'] }}</span>
            </button>
        @endforeach
    </div>

    @foreach ($tabs as $i => $company)
        <section class="lv-company {{ $i === 0 ? 'is-active' : '' }}" data-company-pane="{{ $company['code'] }}" role="tabpanel">

            @if ($company['departments']->isEmpty())
                <div class="lv-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    <p>No {{ Str::plural($noun) }} for {{ $company['code'] }} in this range.</p>
                </div>
            @else
                <div class="lv-tabs lv-dept-tabs" role="tablist" aria-label="{{ $company['code'] }} departments">
                    @foreach ($company['departments'] as $j => $dept)
                        <button type="button"
                                class="lv-tab {{ $j === 0 ? 'is-active' : '' }}"
                                role="tab"
                                aria-selected="{{ $j === 0 ? 'true' : 'false' }}"
                                data-dept="{{ $dept['key'] }}">
                            {{ $dept['name'] }}
                            <span class="lv-count">{{ number_format($dept['count']) }}</span>
                            <span class="ap-due" title="Awaiting Accounting acknowledgement" @if (!$dept['due']) hidden @endif>{{ $dept['due'] }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($company['departments'] as $j => $dept)
                    <div class="lv-dept {{ $j === 0 ? 'is-active' : '' }}" data-dept-pane="{{ $dept['key'] }}" role="tabpanel">
                        <div class="lv-table-wrap">
                            <table class="lv-table">
                                <thead>
                                    <tr>
                                        @foreach ($headers as $label => $class)
                                            <th class="{{ $class }}">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                {{-- Rows are drawn from #{{ $dataId }} when the tab is opened --}}
                                <tbody data-dept-body="{{ $dept['key'] }}"></tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif

        </section>
    @endforeach

    <div class="lv-empty" id="lvNoResults" hidden>
        <i class="fa-solid fa-magnifying-glass"></i>
        <p>No {{ Str::plural($noun) }} match your search.</p>
    </div>

</div>

<script type="application/json" id="{{ $dataId }}">@json($tabs->flatMap(fn ($c) => $c['departments']->mapWithKeys(fn ($d) => [$d['key'] => ['company' => $c['code'], 'rows' => $d['rows']]])))</script>
