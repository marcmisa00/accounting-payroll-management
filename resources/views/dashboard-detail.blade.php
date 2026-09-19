@extends('layouts.app')

@section('title', $company . ' - ' . $bankLabel . ' - Accounting Portal')
@section('page-title', $company . ' · ' . $bankLabel)

@section('styles')
<style>
    .crumbs {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }

    .crumbs a {
        color: var(--crumbs-link);
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }

    .crumbs a:hover {
        text-decoration: underline;
    }

    .crumbs .period {
        color: var(--period-color);
        font-size: 15px;
        transition: color 0.2s;
    }

    .panel {
        background: var(--panel-bg);
        border-radius: 14px;
        box-shadow: var(--panel-shadow);
        overflow-x: auto;
        transition: background 0.2s, box-shadow 0.2s;
    }

    table.emp {
        width: 100%;
        border-collapse: collapse;
        min-width: 720px;
    }

    table.emp th {
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: var(--table-th-color);
        padding: 14px 20px;
        border-bottom: 1px solid var(--table-border);
        transition: color 0.2s, border-color 0.2s;
    }

    table.emp td {
        padding: 12px 20px;
        border-bottom: 1px solid var(--table-row-border);
        font-size: 15px;
        color: var(--text-primary);
        transition: color 0.2s, border-color 0.2s;
    }

    table.emp .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    table.emp tbody tr:hover {
        background: var(--table-hover-bg);
        transition: background 0.15s;
    }

    table.emp tfoot td {
        background: var(--table-footer-bg);
        color: #fff;
        font-weight: 700;
        border-bottom: none;
        transition: background 0.2s;
    }

    .muted {
        color: var(--muted-color);
        transition: color 0.2s;
    }

    .notice {
        background: var(--notice-bg);
        border-left: 4px solid var(--notice-border);
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        color: var(--notice-text);
        font-size: 14px;
        transition: background 0.2s, border-color 0.2s, color 0.2s;
    }

    .empty {
        padding: 40px 24px;
        text-align: center;
        color: var(--empty-color);
        transition: color 0.2s;
    }

    /* ====== LIGHT MODE DEFAULTS ====== */
    :root {
        --crumbs-link: #4b79a1;
        --period-color: #555;
        --panel-bg: #fff;
        --panel-shadow: 0 2px 8px rgba(0,0,0,0.06);
        --table-th-color: #777;
        --table-border: #e8ebef;
        --table-row-border: #f0f2f5;
        --table-hover-bg: #f7f9fb;
        --table-footer-bg: #283e51;
        --muted-color: #94a3b8;
        --notice-bg: #fff7e6;
        --notice-border: #e0a030;
        --notice-text: #6b4a00;
        --empty-color: #777;
    }

    /* ====== DARK MODE OVERRIDES ====== */
    body.dark-mode {
        --crumbs-link: #8ab4f0;
        --period-color: #a0b0c0;
        --panel-bg: #1e2a36;
        --panel-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        --table-th-color: #8a9aa8;
        --table-border: #2a3a4a;
        --table-row-border: #2a3a4a;
        --table-hover-bg: #2a3a4a;
        --table-footer-bg: #1a2530;
        --muted-color: #6a7a8a;
        --notice-bg: #2a2410;
        --notice-border: #c98a2b;
        --notice-text: #e0c080;
        --empty-color: #8a9aa8;
    }
</style>
@endsection

@section('content')

    <div class="crumbs">
        <a href="{{ route('dashboard', ['period' => $period->id]) }}">&larr; Back to dashboard</a>
        <div class="period">
            Payroll period: {{ date('F d, Y', strtotime($period->periodfrom)) }} – {{ date('F d, Y', strtotime($period->periodto)) }}
        </div>
    </div>

    @if ($noBank)
        <div class="notice">
            These employees have no bank set in their payroll record, so they can't be paid by bank transfer until one is added.
        </div>
    @endif

    <div class="panel">
        @if ($employees->isEmpty())
            <div class="empty">
                {{ $noBank ? 'Every employee in this company has a bank set.' : 'No employees found for this bank.' }}
            </div>
        @else
            <table class="emp">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Employee ID</th>
                        <th>Employee name</th>
                        <th>Team</th>
                        <th>Account number</th>
                        <th class="num">Net pay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $i => $e)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $e->idno }}</td>
                            <td>
                                <strong>{{ $e->lastname }}</strong>, {{ $e->firstname }}
                                @if (!empty($e->suffix) && $e->suffix !== 'N/A') {{ $e->suffix }} @endif
                            </td>
                            <td>{{ $e->department }}</td>
                            <td>
                                @if ($e->account_number !== '')
                                    {{ $e->account_number }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="num">{{ number_format($e->net_pay, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="num">Total net pay</td>
                        <td class="num">{{ number_format($total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

@endsection