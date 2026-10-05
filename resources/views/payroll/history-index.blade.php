@extends('layouts.app')

@section('content')

@vite(['resources/css/history-index.css'])

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

            <form method="POST" action="{{ route('payroll.history.select') }}">

                @csrf

                <div class="history-selection">

                    <label for="period">
                        Payroll Period
                    </label>

                    <div class="history-select-wrapper">
                        <select name="period" id="period" required>
                            <option value="">
                                -- Select Payroll Period --
                            </option>

                            @foreach ($payrolls as $payroll)
                                <option value="{{ $payroll->id }}">
                                    Payroll #{{ $payroll->id }}
                                    —
                                    {{ date('M d, Y', strtotime($payroll->periodfrom)) }}
                                    -
                                    {{ date('M d, Y', strtotime($payroll->periodto)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="history-selection-actions">

                    <button type="submit" class="history-view-button">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        View History
                    </button>

                </div>

            </form>

        @endif

    </div>

</div>


</div>

@endsection
