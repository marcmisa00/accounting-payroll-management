{{-- Company / department tabs and the credits table, shared by Leave Credits and Monthly Leave Credits. Rows are drawn by leave-credits.js from #leaveCreditData. --}}
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
            </button>
        @endforeach
    </div>

    @foreach ($tabs as $i => $company)
        <section class="lv-company {{ $i === 0 ? 'is-active' : '' }}" data-company-pane="{{ $company['code'] }}" role="tabpanel">

            @if ($company['departments']->isEmpty())
                <div class="lv-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    <p>No employees for {{ $company['code'] }}{{ $emptyNote }}.</p>
                </div>
            @else
                {{-- Department tabs --}}
                <div class="lv-tabs lv-dept-tabs" role="tablist" aria-label="{{ $company['code'] }} departments">
                    @foreach ($company['departments'] as $j => $dept)
                        <button type="button"
                                class="lv-tab {{ $j === 0 ? 'is-active' : '' }}"
                                role="tab"
                                aria-selected="{{ $j === 0 ? 'true' : 'false' }}"
                                data-dept="{{ $dept['key'] }}">
                            {{ $dept['name'] }}
                            <span class="lv-count">{{ number_format($dept['count']) }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($company['departments'] as $j => $dept)
                    <div class="lv-dept {{ $j === 0 ? 'is-active' : '' }}" data-dept-pane="{{ $dept['key'] }}" role="tabpanel">
                        <div class="lv-table-wrap">
                            <table class="lv-table lc-table">
                                <thead>
                                    <tr>
                                        {{-- Click a header to sort --}}
                                        <th rowspan="2" class="lc-sortable" data-sort-col="0">Emp ID</th>
                                        <th rowspan="2" class="lc-sortable" data-sort-col="1">Employee Name</th>
                                        <th rowspan="2" class="lc-sortable" data-sort-col="2">Eligibility</th>
                                        <th rowspan="2" class="lc-sortable" data-sort-col="3">Length of Service</th>
                                        <th rowspan="2" class="lc-sortable" data-sort-col="4">Period (From - To)</th>
                                        @foreach ($leaves as $label)
                                            <th colspan="3" class="lc-group">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                    <tr class="lc-sub">
                                        @foreach ($leaves as $label)
                                            <th class="num lc-group-start">Credits</th>
                                            <th class="num">Used</th>
                                            <th class="num">Remaining</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                {{-- Rows are drawn by leave-credits.js from #leaveCreditData when the tab is opened --}}
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
        <p>No employees match your search.</p>
    </div>

<script type="application/json" id="leaveCreditData">@json($tabs->flatMap(fn ($c) => $c['departments']->mapWithKeys(fn ($d) => [$d['key'] => ['company' => $c['code'], 'rows' => $d['rows']]])))</script>
