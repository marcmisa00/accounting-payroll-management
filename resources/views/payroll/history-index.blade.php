@extends('layouts.app')

@section('content')

@vite(['resources/css/payroll-history.css'])

<div class="page-header-row">
    <span class="title">
        <i class="fa-solid fa-clock-rotate-left"></i>
        PAYROLL HISTORY
    </span>
</div>

<div class="centered-container">

    <div class="content-panel">

        <div class="panel-heading">
            <h4>Select Payroll Period</h4>

            <p>
                Select a payroll period to view all Add-on, Deduction,
                and Edit Time changes made during that payroll.
            </p>
        </div>

        <div class="panel-body">

            @if ($payrolls->isEmpty())

                <div class="history-empty">
                    <i class="fa-solid fa-circle-info"></i>

                    <p>
                        No payroll periods found.
                    </p>
                </div>

            @else

                <div class="history-payroll-list">

                    @foreach ($payrolls as $payroll)

                        <a href="{{ route('payroll.history', [
                            'payroll' => $payroll->id,
                            'company' => $company,
                            'dept'    => $deptId,
                        ]) }}"
                           class="history-payroll-item">

                            <div class="history-payroll-icon">
                                <i class="fa-solid fa-calendar-days"></i>
                            </div>

                            <div class="history-payroll-info">

                                <strong>
                                    Payroll #{{ $payroll->id }}
                                </strong>

                                <span>
                                    {{ date('M d, Y', strtotime($payroll->periodfrom)) }}
                                    -
                                    {{ date('M d, Y', strtotime($payroll->periodto)) }}
                                </span>

                            </div>

                            <div class="history-payroll-arrow">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>

                        </a>

                    @endforeach

                </div>

            @endif

        </div>

    </div>

</div>

@endsection