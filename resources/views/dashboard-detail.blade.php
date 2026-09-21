@extends('layouts.app')

@section('title', $company . ' - ' . $bankLabel . ' - Accounting Portal')
@section('page-title', $company . ' · ' . $bankLabel)

@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard-d.css') }}">
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