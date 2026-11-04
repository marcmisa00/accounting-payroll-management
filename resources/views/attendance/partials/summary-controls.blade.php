{{-- Controls shared by the Attendance Summary and the Missed Logs / OB Summary (plain GET form). --}}
<form method="GET" action="{{ $action }}" class="att-sum-controls">
    <input type="hidden" name="company" value="{{ $company }}">
    @if ($department)
        <input type="hidden" name="department" value="{{ $department }}">
    @endif

    <a href="{{ route('attendance.index', array_filter(['company' => $company, 'from' => $from, 'to' => $to, 'department' => $department])) }}" class="att-sum-btn">
        <i class="fa-solid fa-arrow-left"></i> Attendance
    </a>

    <input type="search" name="search" value="{{ $search }}" class="att-sum-input att-sum-search" placeholder="Search name or ID..." aria-label="Search employee">

    <div class="att-sum-filter">
        <span class="att-sum-label">Date Range</span>
        <label class="att-sum-label" for="sum-from">From</label>
        <input id="sum-from" type="date" name="from" value="{{ $from }}" class="att-sum-input" required>
        <label class="att-sum-label" for="sum-to">To</label>
        <input id="sum-to" type="date" name="to" value="{{ $to }}" class="att-sum-input" required>
        <button type="submit" class="att-sum-btn att-sum-btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
    </div>

    @if ($withIncidents ?? false)
        <label class="att-legend-check">
            <input type="checkbox" name="incidents" value="1" @checked($incidents) onchange="this.form.submit()"> With incidents only
        </label>
    @endif
    <label class="att-legend-check">
        <input type="checkbox" name="am" value="1" @checked($am) onchange="this.form.submit()"> Morning Shift
    </label>
    <label class="att-legend-check">
        <input type="checkbox" name="gy" value="1" @checked($gy) onchange="this.form.submit()"> Graveyard
    </label>
    @unless ($withIncidents ?? false)
        <label class="att-legend-check">
            <input type="checkbox" name="fixed" value="1" @checked($fixed) onchange="this.form.submit()"> Fixed / Daily
        </label>
    @endunless
</form>

@if ($errors->any())
    <div class="att-alert" role="alert">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span>{{ $errors->first() }}</span>
    </div>
@endif
@if ($rangeCapped)
    <div class="att-alert att-sum-warn" role="status">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Showing the first {{ \App\Http\Controllers\AttendanceController::MAX_RANGE_DAYS }} days only. Pick a shorter range to see the rest.</span>
    </div>
@endif
