@extends('layouts.app')

@section('title', 'Dashboard - Accounting Portal')
@section('page-title', 'Dashboard')


@vite(['resources/css/dashboard.css'])
@section('content')
@php
    $employeeTotal = $companies->sum('employees');
    $bankTotal     = $companies->sum(fn ($c) => $c->banks->count());
    $periodLabel   = $period
        ? date('M d', strtotime($period->periodfrom)) . ' – ' . date('M d, Y', strtotime($period->periodto))
        : 'No period selected';
    $companyKey    = fn ($name) => strtolower(preg_replace('/[^A-Za-z0-9]/', '', $name));
    $previousLabel = $previous
        ? date('M d', strtotime($previous->periodfrom)) . ' – ' . date('M d, Y', strtotime($previous->periodto))
        : null;

    // +/- against the previous period: null when there is nothing to compare with
    $delta = function ($now, $before) {
        if ($before === null || round((float) $before, 2) == 0) {
            return null;
        }
        $diff = round((float) $now - (float) $before, 2);

        return (object) [
            'diff'    => $diff,
            'percent' => $diff / abs((float) $before) * 100,
            'dir'     => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
        ];
    };
    $grandDelta = $delta($grandTotal, $previousGrandTotal);
@endphp

<div class="dash">

    {{-- ===================== Hero stats ===================== --}}
    <div class="stats">
        <div class="stat stat-hero reveal">
            <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="stat-label">Net pay this period</div>
            <div class="stat-value">₱{{ number_format($grandTotal, 2) }}</div>
            @if ($grandDelta)
                <div class="delta delta-{{ $grandDelta->dir }}" title="Compared with {{ $previousLabel }}">
                    <i class="fa-solid {{ $grandDelta->dir === 'up' ? 'fa-arrow-trend-up' : ($grandDelta->dir === 'down' ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i>
                    {{ $grandDelta->diff > 0 ? '+' : ($grandDelta->diff < 0 ? '−' : '') }}₱{{ number_format(abs($grandDelta->diff), 2) }}
                    ({{ $grandDelta->diff > 0 ? '+' : ($grandDelta->diff < 0 ? '−' : '') }}{{ number_format(abs($grandDelta->percent), 1) }}%)
                </div>
            @elseif ($period)
                <div class="delta delta-none">No previous payroll to compare</div>
            @endif
            <div class="stat-foot">
                <i class="fa-regular fa-calendar"></i> {{ $periodLabel }}
                @if ($period && $period->id == $currentId)
                    <span class="badge">Current</span>
                @endif
            </div>
        </div>

        <div class="stat reveal">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-label">Employees paid</div>
            <div class="stat-value">{{ number_format($employeeTotal) }}</div>
            <div class="stat-foot">Across {{ $companies->count() }} {{ Str::plural('company', $companies->count()) }}</div>
        </div>

        <div class="stat reveal">
            <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="stat-label">Net pay in {{ $year }}</div>
            <div class="stat-value">₱{{ number_format($chart['overall'], 2) }}</div>
            <div class="stat-foot">All {{ count($chart['totals']) }} companies</div>
        </div>

        <div class="stat reveal">
            <div class="stat-icon"><i class="fa-solid fa-building-columns"></i></div>
            <div class="stat-label">Bank groups</div>
            <div class="stat-value">{{ number_format($bankTotal) }}</div>
            <div class="stat-foot">For the selected period</div>
        </div>
    </div>

    {{-- ===================== Yearly graphs ===================== --}}
    <div class="section-head reveal">
        <div>
            <h2>Yearly overview (test)</h2>
            <div class="sub">Monthly net pay by company in {{ $year }}</div>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="picker">
            @if ($period)
                <input type="hidden" name="period" value="{{ $period->id }}">
            @endif
            <label class="small" for="year">Year</label>
            <div class="select-wrap">
                <select name="year" id="year" class="pick" onchange="this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit">Show</button></noscript>
        </form>
    </div>

    <div class="graphs">
        @foreach ($chart['totals'] as $name => $total)
            <div class="graph reveal co-{{ $companyKey($name) }}">
                <div class="graph-head">
                    <div class="graph-title">
                        <span class="dot"></span>
                        <h3>{{ $name }}</h3>
                    </div>
                    <div class="amt">₱{{ number_format($total, 2) }}</div>
                </div>
                <div class="canvas-wrap">
                    <canvas data-company="{{ $name }}" role="img"
                            aria-label="Monthly net pay for {{ $name }} in {{ $year }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ===================== Period summary ===================== --}}
    <div class="section-head reveal">
        <div>
            <h2>Period breakdown</h2>
            <div class="sub">Net pay by company and bank. Click a bank to see its employees.</div>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="picker">
            <label class="small" for="period">Payroll period</label>
            <div class="select-wrap">
                <select name="period" id="period" class="pick pick-wide" onchange="this.form.submit()">
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}" @selected($period && $p->id == $period->id)>
                            {{ date('M d, Y', strtotime($p->periodfrom)) }} – {{ date('M d, Y', strtotime($p->periodto)) }}{{ $p->id == $currentId ? ' (Current)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit">Show</button></noscript>
        </form>
    </div>

    @if ($companies->isEmpty())
        <div class="empty reveal">
            <i class="fa-regular fa-folder-open"></i>
            <p>No payroll data found for this period. Pick another period from the list above.</p>
        </div>
    @else
        <div class="companies">
            @foreach ($companies as $companyName => $company)
                <section class="company reveal co-{{ $companyKey($companyName) }}">
                    <div class="company-head">
                        <div>
                            <h2>{{ $companyName }}</h2>
                            <div class="meta">
                                <i class="fa-solid fa-user-group"></i>
                                {{ $company->employees }} {{ Str::plural('employee', $company->employees) }}
                            </div>
                        </div>
                        <div class="total">
                            <span>Net pay</span>
                            ₱{{ number_format($company->net_pay, 2) }}
                            @php
                                $cd = $delta($company->net_pay, $previous ? ($previousTotals[$companyName] ?? 0) : null);
                            @endphp
                            @if ($cd)
                                <div class="delta delta-{{ $cd->dir }}" title="Compared with {{ $previousLabel }}">
                                    <i class="fa-solid {{ $cd->dir === 'up' ? 'fa-arrow-trend-up' : ($cd->dir === 'down' ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i>
                                    {{ $cd->diff > 0 ? '+' : ($cd->diff < 0 ? '−' : '') }}₱{{ number_format(abs($cd->diff), 2) }}
                                    ({{ $cd->diff > 0 ? '+' : ($cd->diff < 0 ? '−' : '') }}{{ number_format(abs($cd->percent), 1) }}%)
                                </div>
                            @else
                                <div class="delta delta-none">No previous payroll</div>
                            @endif
                        </div>
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
                                    <td class="num strong">₱{{ number_format($row->net_pay, 2) }}</td>
                                    <td class="num go" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    @endif

</div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            if (typeof Chart === 'undefined') return;

            const series = @json($chart['series']);
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

            // Theme class lives on <html> (set by the layout)
            const isDark = () => document.documentElement.classList.contains('dark-mode');
            const theme = () => isDark()
                ? { grid: 'rgba(148, 163, 184, 0.10)', text: '#8b9bb4', tipBg: '#0f172a', tipText: '#e2e8f0', tipBorder: 'rgba(148, 163, 184, 0.25)' }
                : { grid: 'rgba(15, 23, 42, 0.06)',    text: '#64748b', tipBg: '#ffffff', tipText: '#0f172a', tipBorder: 'rgba(15, 23, 42, 0.10)' };

            // One muted colour per company, matching the .co-* dots in dashboard.css
            const palette = {
                light: { NESI1: '#4f46e5', NESI2: '#0f766e', NEWIND: '#b45309' },
                dark:  { NESI1: '#818cf8', NESI2: '#2dd4bf', NEWIND: '#f59e0b' }
            };
            const colorFor = name => (isDark() ? palette.dark : palette.light)[name] || (isDark() ? '#818cf8' : '#4f46e5');

            const short = v => v >= 1e6 ? '₱' + (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M'
                             : v >= 1e3 ? '₱' + Math.round(v / 1e3) + 'K'
                             : '₱' + v;
            const full = v => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const chartInstances = [];

            document.querySelectorAll('canvas[data-company]').forEach(function (canvas) {
                const name = canvas.dataset.company;
                const color = colorFor(name);
                const t = theme();

                const chart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: months,
                        datasets: [{
                            data: series[name],
                            backgroundColor: color + 'cc',
                            hoverBackgroundColor: color,
                            borderRadius: 4,
                            borderSkipped: false,
                            maxBarThickness: 20
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 900, easing: 'easeOutQuart' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: { label: ctx => full(ctx.parsed.y) },
                                backgroundColor: t.tipBg,
                                titleColor: t.tipText,
                                bodyColor: t.tipText,
                                borderColor: t.tipBorder,
                                borderWidth: 1,
                                padding: 10,
                                cornerRadius: 10,
                                displayColors: false,
                                titleFont: { weight: '600' },
                                bodyFont: { weight: '700', size: 13 }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: t.text, font: { size: 11 } }
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: t.grid },
                                ticks: { maxTicksLimit: 5, callback: v => short(v), color: t.text, font: { size: 11 } }
                            }
                        }
                    }
                });

                chartInstances.push(chart);
            });

            // Re-color charts when the theme changes
            const observer = new MutationObserver(function (mutations) {
                if (!mutations.some(m => m.attributeName === 'class')) return;
                const t = theme();
                chartInstances.forEach(function (chart) {
                    const color = colorFor(chart.canvas.dataset.company);
                    chart.data.datasets[0].backgroundColor = color + 'cc';
                    chart.data.datasets[0].hoverBackgroundColor = color;
                    chart.options.scales.y.grid.color = t.grid;
                    chart.options.scales.y.ticks.color = t.text;
                    chart.options.scales.x.ticks.color = t.text;
                    chart.options.plugins.tooltip.backgroundColor = t.tipBg;
                    chart.options.plugins.tooltip.titleColor = t.tipText;
                    chart.options.plugins.tooltip.bodyColor = t.tipText;
                    chart.options.plugins.tooltip.borderColor = t.tipBorder;
                    chart.update('none');
                });
            });

            observer.observe(document.documentElement, { attributes: true });
        })();
    </script>

@endsection
