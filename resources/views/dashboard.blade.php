@extends('layouts.app')

@section('title', 'Dashboard - Accounting Portal')
@section('page-title', 'Dashboard')



@section('content')
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>NESI Accounting Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
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