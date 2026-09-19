@extends('layouts.app')

@section('title', 'Dashboard - Accounting Portal')
@section('page-title', 'Dashboard')

@section('styles')
<style>
    select.pick {
        padding: 7px 10px;
        font-size: 14px;
        border: 2px solid var(--pick-border);
        border-radius: 8px;
        background: var(--pick-bg);
        color: var(--pick-text);
        max-width: 100%;
        transition: background 0.2s, border-color 0.2s, color 0.2s;
    }

    select.pick:focus {
        outline: none;
        border-color: #4b79a1;
    }

    label.small {
        display: block;
        font-size: 12px;
        color: var(--label-color);
        margin-bottom: 4px;
        transition: color 0.2s;
    }

    /* ---------- Yearly graphs ---------- */
    .section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
    }

    .section-head h2 {
        margin: 0;
        font-size: 18px;
        color: var(--heading-color);
        transition: color 0.2s;
    }

    .section-head .sub {
        font-size: 13px;
        color: var(--sub-color);
        margin-top: 2px;
        transition: color 0.2s;
    }

    .section-head .sub strong {
        color: var(--heading-color);
        font-size: 15px;
        transition: color 0.2s;
    }

    .graphs {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .graph {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 16px 18px 12px;
        box-shadow: var(--card-shadow);
        transition: background 0.2s, box-shadow 0.2s;
    }

    .graph-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 10px;
    }

    .graph-head h3 {
        margin: 0;
        font-size: 15px;
        color: var(--heading-color);
        transition: color 0.2s;
    }

    .graph-head .amt {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
        transition: color 0.2s;
    }

    .canvas-wrap {
        position: relative;
        height: 210px;
    }

    /* ---------- Period summary (compact) ---------- */
    .divider {
        border: 0;
        border-top: 1px solid var(--divider-color);
        margin: 0 0 24px;
        transition: border-color 0.2s;
    }

    .toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .grand {
        text-align: right;
    }

    .grand .label {
        font-size: 12px;
        color: var(--sub-color);
        transition: color 0.2s;
    }

    .grand .value {
        font-size: 20px;
        font-weight: 700;
        color: var(--heading-color);
        transition: color 0.2s;
    }

    .companies {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 16px;
        align-items: start;
    }

    .company {
        background: var(--card-bg);
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        transition: background 0.2s, box-shadow 0.2s;
    }

    .company-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        background: var(--company-head-bg);
        color: #fff;
        transition: background 0.2s;
    }

    .company-head h2 {
        margin: 0;
        font-size: 15px;
    }

    .company-head .meta {
        font-size: 12px;
        opacity: 0.75;
    }

    .company-head .total {
        font-size: 16px;
        font-weight: 700;
        white-space: nowrap;
    }

    .bank-table {
        width: 100%;
        border-collapse: collapse;
    }

    .bank-table th {
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: var(--table-header-color);
        padding: 8px 16px;
        border-bottom: 1px solid var(--table-border);
        transition: color 0.2s, border-color 0.2s;
    }

    .bank-table th.num,
    .bank-table td.num {
        text-align: right;
    }

    .bank-table td {
        padding: 9px 16px;
        border-bottom: 1px solid var(--table-row-border);
        font-size: 13px;
        color: var(--text-primary);
        transition: color 0.2s, border-color 0.2s;
    }

    .bank-table tbody tr:last-child td {
        border-bottom: none;
    }

    .bank-table tbody tr.clickable {
        cursor: pointer;
    }

    .bank-table tbody tr.clickable:hover,
    .bank-table tbody tr.clickable:focus-within {
        background: var(--table-hover-bg);
        transition: background 0.15s;
    }

    .bank-table a {
        color: var(--link-color);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.2s;
    }

    .bank-table a:focus-visible {
        outline: 2px solid #4b79a1;
        outline-offset: 2px;
    }

    .bank-table .go {
        width: 16px;
        color: #4b79a1;
        padding-left: 0;
    }

    .empty {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
        color: var(--sub-color);
        font-size: 14px;
        box-shadow: var(--card-shadow);
        transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    }

    /* ====== DARK MODE VARIABLES for this page ====== */
    body.dark-mode {
        --pick-bg: #1e2a36;
        --pick-border: #3a4a5a;
        --pick-text: #e8edf2;
        --label-color: #a0b0c0;
        --heading-color: #d0dae8;
        --sub-color: #8a9aa8;
        --card-bg: #1e2a36;
        --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        --company-head-bg: #1a2530;
        --table-header-color: #8a9aa8;
        --table-border: #2a3a4a;
        --table-row-border: #2a3a4a;
        --table-hover-bg: #2a3a4a;
        --link-color: #8ab4f0;
        --divider-color: #2a3a4a;
    }

    /* Light mode defaults (already in :root from parent) */
    :root {
        --pick-bg: #fff;
        --pick-border: #d9dee5;
        --pick-text: #222;
        --label-color: #666;
        --heading-color: #283e51;
        --sub-color: #666;
        --card-bg: #fff;
        --card-shadow: 0 2px 8px rgba(0,0,0,0.06);
        --company-head-bg: #283e51;
        --table-header-color: #777;
        --table-border: #e8ebef;
        --table-row-border: #f0f2f5;
        --table-hover-bg: #eef3f8;
        --link-color: #283e51;
        --divider-color: #dfe4ea;
    }
</style>
@endsection

@section('content')

    {{-- ===================== Yearly graphs ===================== --}}
    <div class="section-head">
        <div>
            <h2>Net pay in {{ $year }}</h2>
            <div class="sub">
                All {{ count($chart['totals']) }} companies: <strong>₱{{ number_format($chart['overall'], 2) }}</strong>
            </div>
        </div>

        <form method="GET" action="{{ route('dashboard') }}">
            @if ($period)
                <input type="hidden" name="period" value="{{ $period->id }}">
            @endif
            <label class="small" for="year">Year</label>
            <select name="year" id="year" class="pick" onchange="this.form.submit()">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
            </select>
            <noscript><button type="submit">Show</button></noscript>
        </form>
    </div>

    <div class="graphs">
        @foreach ($chart['totals'] as $name => $total)
            <div class="graph">
                <div class="graph-head">
                    <h3>{{ $name }}</h3>
                    <div class="amt">₱{{ number_format($total, 2) }}</div>
                </div>
                <div class="canvas-wrap">
                    <canvas data-company="{{ $name }}" role="img"
                            aria-label="Monthly net pay for {{ $name }} in {{ $year }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    <hr class="divider">

    {{-- ===================== Period summary ===================== --}}
    <div class="toolbar">
        <form method="GET" action="{{ route('dashboard') }}">
            <label class="small" for="period">Payroll period</label>
            <select name="period" id="period" class="pick" style="min-width: 280px;" onchange="this.form.submit()">
                @foreach ($periods as $p)
                    <option value="{{ $p->id }}" @selected($period && $p->id == $period->id)>
                        {{ date('M d, Y', strtotime($p->periodfrom)) }} – {{ date('M d, Y', strtotime($p->periodto)) }}{{ $p->id == $currentId ? ' (Current)' : '' }}
                    </option>
                @endforeach
            </select>
            <noscript><button type="submit">Show</button></noscript>
        </form>

        <div class="grand">
            <div class="label">Total net pay for this period</div>
            <div class="value">₱{{ number_format($grandTotal, 2) }}</div>
        </div>
    </div>

    @if ($companies->isEmpty())
        <div class="empty">
            No payroll data found for this period. Pick another period from the list above.
        </div>
    @else
        <div class="companies">
            @foreach ($companies as $companyName => $company)
                <section class="company">
                    <div class="company-head">
                        <div>
                            <h2>{{ $companyName }}</h2>
                            <div class="meta">{{ $company->employees }} {{ Str::plural('employee', $company->employees) }}</div>
                        </div>
                        <div class="total">₱{{ number_format($company->net_pay, 2) }}</div>
                    </div>

                    <table class="bank-table">
                        <thead>
                            <tr>
                                <th>Bank</th>
                                <th class="num">Employees</th>
                                <th class="num">Net pay</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($company->banks as $row)
                                @php
                                    $url = route('dashboard.detail', [
                                        'period'  => $period->id,
                                        'company' => $companyName,
                                        'bank'    => $row->bank,
                                    ]);
                                @endphp
                                <tr class="clickable" onclick="window.location='{{ $url }}'">
                                    <td>
                                        <a href="{{ $url }}">{{ $row->bank === \App\Http\Controllers\DashboardController::NO_BANK ? 'No bank info' : $row->bank }}</a>
                                    </td>
                                    <td class="num">{{ $row->employees }}</td>
                                    <td class="num">₱{{ number_format($row->net_pay, 2) }}</td>
                                    <td class="num go" aria-hidden="true">&rsaquo;</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            if (typeof Chart === 'undefined') return;

            const series = @json($chart['series']);
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

            // Color scheme aware chart colors
            const isDark = () => document.body.classList.contains('dark-mode');
            const gridColor = () => isDark() ? '#2a3a4a' : '#eef1f4';
            const textColor = () => isDark() ? '#a0b0c0' : '#666';

            // Base colors for each company (remain same in both modes)
            const colors = { NESI1: '#4b79a1', NESI2: '#2f8f83', NEWIND: '#c98a2b' };

            const short = v => v >= 1e6 ? '₱' + (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M'
                             : v >= 1e3 ? '₱' + Math.round(v / 1e3) + 'K'
                             : '₱' + v;
            const full = v => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Store chart instances to update on theme change
            const chartInstances = [];

            document.querySelectorAll('canvas[data-company]').forEach(function (canvas) {
                const name = canvas.dataset.company;

                const chart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: months,
                        datasets: [{
                            data: series[name],
                            backgroundColor: colors[name] || '#4b79a1',
                            borderRadius: 3,
                            maxBarThickness: 22
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: { label: ctx => full(ctx.parsed.y) },
                                backgroundColor: isDark() ? '#2a3a4a' : '#fff',
                                titleColor: isDark() ? '#e8edf2' : '#222',
                                bodyColor: isDark() ? '#e8edf2' : '#222',
                                borderColor: isDark() ? '#3a4a5a' : '#ddd',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: textColor() }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor() },
                                ticks: { maxTicksLimit: 5, callback: v => short(v), color: textColor() }
                            }
                        }
                    }
                });

                chartInstances.push(chart);
            });

            // Update charts when theme changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        chartInstances.forEach(function(chart) {
                            chart.options.scales.y.grid.color = gridColor();
                            chart.options.scales.y.ticks.color = textColor();
                            chart.options.scales.x.ticks.color = textColor();
                            chart.options.plugins.tooltip.backgroundColor = isDark() ? '#2a3a4a' : '#fff';
                            chart.options.plugins.tooltip.titleColor = isDark() ? '#e8edf2' : '#222';
                            chart.options.plugins.tooltip.bodyColor = isDark() ? '#e8edf2' : '#222';
                            chart.options.plugins.tooltip.borderColor = isDark() ? '#3a4a5a' : '#ddd';
                            chart.update('none');
                        });
                    }
                });
            });

            observer.observe(document.body, { attributes: true });
        })();
    </script>

@endsection