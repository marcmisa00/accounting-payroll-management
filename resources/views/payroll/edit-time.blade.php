@extends('layouts.app')

@section('content')

@if (session('success'))
    <script>alert(@json(session('success')));</script>
@endif

@vite(['resources/css/edit-time-payroll.css'])

@php
    $backUrl = route('payroll.edit.show', [
        'payroll' => $payroll->id,
        'idno'    => $idno,
        'company' => $company,
        'dept'    => $deptId
    ]);

    // OT and ND live in the same override table/page as everything else now
    // — no separate mechanism. OT adds minutes to the calculated overtime;
    // ND replaces the auto-detected hours outright.
    $fields = [
        ['ot_minutes',   'OT Override (minutes)',       'Minutes added to the calculated overtime for this day'],
        ['ndhrs',        'ND Override (hours)',         'Replaces the auto-detected night differential hours for this day'],
        ['totalwo',      'Total Hrs',                    null],
        ['reghrs',       'Reg Hrs',                      null],
        ['ratday',       'Rate/Day',                     'Base pay for this day'],
        ['regdaysot',    'Reg Days OT Rate',             null],
        ['ndrate',       'ND Rate',                      null],
        ['spholiday',    'Special Non-Working Holiday',  null],
        ['spholidayot',  'OT Special Holiday',           null],
        ['regholidayot', 'OT Regular Holiday',           null],
        ['regholiday',   'Regular Holiday',              null],
        ['totalpay',     'Total Pay',                    'Overriding this directly wins over everything else on this day'],
    ];

    /*
     * These values come directly from attendance.
     *
     * idle  = editable
     * ottime = display only
     */
    $idleRaw = $attendanceRecord->idle ?? null;
    $otTime  = $attendanceRecord->ottime ?? 0;

    $idleValue = 0;

    if ($idleRaw) {
        [$hours, $minutes, $seconds] = array_map(
            'intval',
            explode(':', $idleRaw)
        );

        $idleValue =
            $hours +
            ($minutes / 60) +
            ($seconds / 3600);
    }
@endphp

<div class="page-header-row">
    <a href="{{ $backUrl }}">
        <i class="fa fa-arrow-left"></i> BACK
    </a>

    <span class="title">
        <i class="fa fa-pencil"></i> EDIT DAY — {{ $row['date'] }}
    </span>
</div>

<div class="centered-container">
    <div class="content-panel">

        <div class="panel-heading">
            <h4>Manual Override</h4>
            <p>
                Leave a field blank to use the calculated value.
                Only fields you fill in here override the engine's result for this one day.
            </p>
        </div>

        <div class="panel-body">

            <div class="horizontal-grid">

                {{-- LEFT: Calculated / Attendance Values --}}
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Calculated</th>
                        </tr>
                    </thead>

                    <tbody>

                        {{-- OT TIME (raw attendance.ottime — not the same as the OT override above) --}}
                        <tr>
                            <td>OT Time (attendance record)</td>
                            <td>
                                {{ number_format((float) $otTime, 2) }}
                            </td>
                        </tr>

                        {{-- IDLE --}}
                        <tr>
                            <td>Idle</td>
                            <td>
                                {{ number_format((float) $idleValue, 2) }}
                            </td>
                        </tr>

                        @foreach ($fields as [$key, $label, $help])
                            <tr>
                                <td>{{ $label }}</td>
                                <td>{{ $calculated[$key] ?? '-' }}</td>
                            </tr>
                        @endforeach

                    </tbody>
                </table>


                {{-- RIGHT: Edit Form --}}
                <form method="POST"
                      action="{{ route('payroll.edit.time.override', [
                          'payroll'    => $payroll->id,
                          'idno'       => $idno,
                          'attendance' => $attendance,
                          'company'    => $company,
                          'dept'       => $deptId
                      ]) }}">

                    @csrf

                    <div class="form-grid">

                        {{-- OT TIME - READ ONLY --}}
                        <div class="form-group">
                            <label class="control-label">
                                OT Time (attendance record)
                            </label>

                            <small class="help-text">
                                This value cannot be edited here. Use "OT Override (minutes)" below to adjust overtime.
                            </small>

                            <input
                                type="number"
                                step="0.01"
                                class="form-control"
                                value="{{ number_format((float) $otTime, 2, '.', '') }}"
                                readonly
                            >
                        </div>


                        {{-- IDLE - EDITABLE --}}
                        <div class="form-group">
                            <label class="control-label">
                                Idle
                            </label>

                            <small class="help-text">
                                Idle hours recorded for this attendance day.
                            </small>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="idle"
                                class="form-control"
                                value="{{ old('idle', $idleValue) }}"
                            >
                        </div>


                        {{-- OT / ND + EXISTING PAYROLL OVERRIDES — ONE UNIFIED LIST --}}
                        @foreach ($fields as [$key, $label, $help])

                            <div class="form-group {{ $help ? 'full-width' : '' }}">

                                <label class="control-label">
                                    {{ $label }}
                                </label>

                                @if ($help)
                                    <small class="help-text">
                                        {{ $help }}
                                    </small>
                                @endif

                                <input
                                    type="number"
                                    step="0.01"
                                    name="{{ $key }}"
                                    class="form-control"
                                    placeholder="Calculated: {{ $calculated[$key] ?? '-' }}"
                                    value="{{ old($key, $override->{$key} ?? '') }}"
                                >

                            </div>

                        @endforeach

                    </div>


                    <div class="action-row">

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i>
                            Save Changes
                        </button>

                        <a href="{{ $backUrl }}" class="btn btn-secondary">
                            Cancel
                        </a>

                    </div>

                </form>

            </div>


            {{-- CLEAR PAYROLL OVERRIDES --}}
            @if ($override)

                <div class="clear-section">

                    <form method="POST"
                          action="{{ route('payroll.edit.time.override.clear', [
                              'payroll'    => $payroll->id,
                              'idno'       => $idno,
                              'attendance' => $attendance,
                              'company'    => $company,
                              'dept'       => $deptId
                          ]) }}"
                          onsubmit="return confirm('Clear every payroll override on this day (including OT and ND) and go back to fully calculated values?')">

                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-danger">
                            <i class="fa fa-undo"></i>
                            Clear all payroll overrides for this day
                        </button>

                    </form>

                    @if ($override->updated_by || $override->updated_at)

                        <p class="last-override-note">

                            Last overridden
                            {{ $override->updated_by ? ' by ' . $override->updated_by : '' }}
                            {{ $override->updated_at ? ' on ' . date('M d, Y h:i A', strtotime($override->updated_at)) : '' }}.

                        </p>

                    @endif

                </div>

            @endif

        </div>

    </div>
</div>

@endsection