@extends('layouts.app')

@section('title', 'Attendance - Accounting Portal')
@section('page-title', 'Attendance Monitoring')

@section('styles')
    @vite(['resources/css/attendance.css'])
@endsection

@section('content')
@php
    // "0", empty and 00:00:00 all mean "no punch" in hris.attendance
    $time = fn ($v) => ($v && $v !== '0' && $v !== '00:00:00') ? date('h:i A', strtotime($v)) : null;

    $statusChip = function (?string $status): array {
        $tokens = array_filter(explode('/', (string) $status));

        return match (true) {
            in_array('ab', $tokens, true)    => ['Absent', 'chip-absent'],
            in_array('sus', $tokens, true)   => ['Suspended', 'chip-sus'],
            in_array('leave', $tokens, true) => ['Leave', 'chip-leave'],
            in_array('work', $tokens, true)  => [in_array('nd', $tokens, true) ? 'Present · ND' : 'Present', 'chip-present'],
            $status === null || $status === '' => ['No status', 'chip-other'],
            default                          => [ucfirst($status), 'chip-other'],
        };
    };

    $companyName = optional($companies->firstWhere('companycode', $company))->companyname;
@endphp

<div class="att">

    {{-- ===================== Filters ===================== --}}
    <form method="GET" action="{{ route('attendance.index') }}" class="att-filters">
        <div class="att-field">
            <label for="company">Company</label>
            <select name="company" id="company" onchange="this.form.department.value=''; this.form.submit()">
                @foreach ($companies as $c)
                    <option value="{{ $c->companycode }}" @selected($c->companycode === $company)>
                        {{ $c->companycode }} — {{ $c->companyname }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="att-field">
            <label for="from">From</label>
            <input type="date" name="from" id="from" value="{{ $from }}" required>
        </div>

        <div class="att-field">
            <label for="to">To</label>
            <input type="date" name="to" id="to" value="{{ $to }}" required>
        </div>

        <div class="att-field">
            <label for="department">Department</label>
            <select name="department" id="department">
                <option value="">All departments</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected((string) $d->id === (string) $department)>{{ $d->department }}</option>
                @endforeach
            </select>
        </div>

        <div class="att-field">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">All statuses</option>
                @foreach (\App\Http\Controllers\AttendanceController::STATUSES as $key => $s)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="att-field att-field-wide">
            <label for="employee">Employee</label>
            <div class="att-input-icon">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" name="employee" id="employee" value="{{ $employee }}" placeholder="Name or employee ID..." autocomplete="off">
            </div>
        </div>

        <div class="att-actions">
            <button type="submit"><i class="fa-solid fa-filter"></i> Show</button>
            <a href="{{ route('attendance.index', ['company' => $company]) }}">Reset</a>
        </div>
    </form>

    @if ($errors->any() || $rangeError)
        <div class="att-alert" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $rangeError ?? $errors->first() }}</span>
        </div>
    @endif

    @if ($summary)
        {{-- ===================== Summary ===================== --}}
        <div class="att-range">
            <strong>{{ $company }}</strong> {{ $companyName ? '· ' . $companyName : '' }}
            <span>{{ date('M d, Y', strtotime($from)) }} – {{ date('M d, Y', strtotime($to)) }}</span>
        </div>

        <div class="att-stats">
            <div class="att-stat">
                <span class="att-stat-icon"><i class="fa-solid fa-users"></i></span>
                <div><div class="att-stat-label">Employees</div><div class="att-stat-value">{{ number_format($summary->employees) }}</div></div>
            </div>
            <div class="att-stat is-present">
                <span class="att-stat-icon"><i class="fa-solid fa-circle-check"></i></span>
                <div><div class="att-stat-label">Present days</div><div class="att-stat-value">{{ number_format((int) $summary->present) }}</div></div>
            </div>
            <div class="att-stat is-absent">
                <span class="att-stat-icon"><i class="fa-solid fa-circle-xmark"></i></span>
                <div><div class="att-stat-label">Absences</div><div class="att-stat-value">{{ number_format((int) $summary->absent) }}</div></div>
            </div>
            <div class="att-stat is-leave">
                <span class="att-stat-icon"><i class="fa-solid fa-umbrella-beach"></i></span>
                <div><div class="att-stat-label">Leaves</div><div class="att-stat-value">{{ number_format((int) $summary->on_leave) }}</div></div>
            </div>
            <div class="att-stat is-sus">
                <span class="att-stat-icon"><i class="fa-solid fa-ban"></i></span>
                <div><div class="att-stat-label">Suspensions</div><div class="att-stat-value">{{ number_format((int) $summary->suspended) }}</div></div>
            </div>
        </div>

        {{-- ===================== Table ===================== --}}
        @if ($records->isEmpty())
            <div class="att-empty">
                <i class="fa-regular fa-calendar-xmark"></i>
                <p>No attendance found for these filters.</p>
            </div>
        @else
            <div class="att-summary">
                Showing <strong>{{ number_format($records->firstItem()) }}–{{ number_format($records->lastItem()) }}</strong>
                of <strong>{{ number_format($records->total()) }}</strong> records
            </div>

            <div class="att-table-wrap">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Time In</th>
                            <th>Lunch Out</th>
                            <th>Lunch In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $r)
                            @php
                                [$chipLabel, $chipClass] = $statusChip($r->status);
                                $name = trim(($r->lastname ?? '') . ', ' . ($r->firstname ?? ''), ', ') ?: $r->idno;
                                // attendance_dls = 1: the row counts for the day after logindate
                                $loginDate = substr((string) $r->logindate, 0, 10);
                                $date = substr((string) $r->work_date, 0, 10);
                                $isDls = (int) $r->attendance_dls === 1;
                                $corrected = $corrections->has($r->idno . '|' . $loginDate);
                                $ot = $time($r->ottime);
                                $idle = ($r->idle && $r->idle !== '00:00:00') ? substr($r->idle, 0, 5) : null;
                            @endphp
                            <tr>
                                <td class="att-date">
                                    {{ date('M d', strtotime($date)) }}
                                    <small>
                                        {{ date('D', strtotime($date)) }}
                                        @if ($isDls)
                                            <span class="att-dls" title="Logged on {{ date('M d, Y', strtotime($loginDate)) }} (Graveyard Shift)">DLS</span>
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <div class="att-person">
                                        <span class="att-avatar" aria-hidden="true">{{ strtoupper(substr($r->firstname ?: $name, 0, 1)) }}</span>
                                        <div>
                                            <strong>{{ $name }}</strong>
                                            <small>{{ $r->idno }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="att-muted">{{ $r->department_name ?? '—' }}</td>
                                @foreach (['loginam', 'logoutam', 'loginpm', 'logoutpm'] as $col)
                                    <td class="att-time">{!! $time($r->{$col}) ? e($time($r->{$col})) : '<span class="att-none">—</span>' !!}</td>
                                @endforeach
                                <td>
                                    <span class="att-chip {{ $chipClass }}">{{ $chipLabel }}</span>
                                    @if ($corrected)
                                        <span class="att-flag" title="This day has an approved missed-log application">
                                            <i class="fa-solid fa-pen-to-square"></i> Missed log
                                        </span>
                                    @endif
                                </td>
                                <td class="att-remarks">
                                    {{ $r->remarks ?: '—' }}
                                    @if ($r->otremarks === 'OT' || $ot)
                                        <small><i class="fa-solid fa-clock"></i> OT{{ $ot ? ' until ' . $ot : '' }}</small>
                                    @endif
                                    @if ($idle)
                                        <small><i class="fa-solid fa-hourglass-half"></i> Idle {{ $idle }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $records->onEachSide(1)->links('payroll.partials.history-pagination') }}
        @endif
    @endif

</div>
@endsection
