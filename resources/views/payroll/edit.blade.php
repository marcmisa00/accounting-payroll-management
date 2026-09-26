@extends('layouts.app')

@section('content')

@if (session('success'))
    <script>alert(@json(session('success')));</script>
@endif
@if (session('error'))
    <script>alert(@json(session('error')));</script>
@endif

@php
    $backUrl = route('payroll.show', ['payroll' => $payroll->id]);
@endphp

<script>
function SubmitDetails() {
    return confirm('Do you wish to submit details?');
}
</script>

<link rel="stylesheet" href="{{ asset('css/edit-payroll.css') }}">
<div class="row">
    <div class="col-lg-12">
        <h4 style="display: flex; align-items: center; justify-content: space-between; text-indent: 10px;">
            <span style="display: flex; align-items: center; gap: 10px;">
                <a id="backLink" href="{{ $backUrl }}"><i class="fa fa-arrow-left"></i> BACK</a>
                <span><i class="fa fa-money"></i> EDIT PAYROLL</span>
            </span>

            <span style="display: flex; align-items: center; gap: 10px; margin-right: 30px;">
                @if ($prevId)
                    <a href="{{ route('payroll.edit.show', ['payroll' => $payroll->id, 'idno' => $prevId, 'company' => $company, 'dept' => $deptId]) }}" class="btn btn-secondary">&laquo; Previous</a>
                @endif
                @if ($nextId)
                    <a href="{{ route('payroll.edit.show', ['payroll' => $payroll->id, 'idno' => $nextId, 'company' => $company, 'dept' => $deptId]) }}" class="btn btn-primary">Next &raquo;</a>
                @endif
            </span>
        </h4>
    </div>
</div>

<div class="centered-container">
    <form class="form-horizontal style-form" method="POST" action="{{ route('payroll.edit.save', ['payroll' => $payroll->id, 'idno' => $idno, 'company' => $company, 'dept' => $deptId]) }}" onsubmit="return SubmitDetails();">
        @csrf

        <div class="content-panel">
            <div class="panel-heading">
                <div class="flex-item-left" style="display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 18px; padding-left: 5px;">
                        <span style="display: block; font-family: Arial, sans-serif; font-size: 18px; color: #333;">
                            <i class="fa fa-user"></i>
                            <strong style="font-size: 18px;">{{ $employee->lastname ?? '' }}, {{ $employee->firstname ?? '' }} {{ $employee->suffix ?? '' }}</strong>
                        </span>
                    </div>
                </div>

                <div class="export-btn" style="display: flex; margin-left: auto; float: right;">
                    @if ($isSaved)
                        @if ($salaryType === 'Rated')
                            <a href="/payslipRated.php?id={{ $payrollId }}" class="btn btn-warning" title="View Payslip" target="_blank" style="float:right; margin-right:10px;"><i class="fa fa-eye"></i></a>
                            <a href="/exporttopdfRated.php?id={{ $payrollId }}" class="btn btn-success" title="Export Payslip" target="_blank" style="float:right; margin-right:10px;"><i class="fa fa-download"></i></a>
                        @elseif ($salaryType === 'Fixed')
                            <a href="/payslip.php?id={{ $payrollId }}" class="btn btn-warning" title="Print Payslip" target="_blank" style="float:right; margin-right:10px;"><i class="fa fa-eye"></i></a>
                            <a href="/exporttopdfFixed.php?id={{ $payrollId }}" class="btn btn-success" title="Export Payslip" target="_blank" style="float:right; margin-right:10px;"><i class="fa fa-download"></i></a>
                        @elseif ($salaryType === 'Daily')
                            <a href="/payslipDaily.php?id={{ $payrollId }}" class="btn btn-warning" title="Print Payslip" target="_blank" style="float:right; margin-right:10px;"><i class="fa fa-eye"></i></a>
                            <a href="/exporttopdfDaily.php?id={{ $payrollId }}" class="btn btn-success" title="Export Payslip" target="_blank" style="float:right; margin-right:10px;"><i class="fa fa-download"></i></a>
                        @endif
                    @endif
                </div>
            </div>

            <div style="display: block; margin-left: 20px; margin-bottom: 10px;">
                <div style="float: left;">
                    <label style="display: block; font-family: Arial, sans-serif; font-size: 14px; color: #333;">
                        <strong>Payroll Period:</strong> {{ $payroll->periodfrom->format('M d, Y') }} to {{ $payroll->periodto->format('M d, Y') }}
                    </label>
                    <div style="display: flex; align-items: center; font-family: Arial, sans-serif; font-size: 14px; color: #333;">
                        <label style="margin-right: 10px;"><strong>Salary Type:</strong></label>
                        <label class="radio-inline" style="margin-right: 10px; margin-top: -10px;">
                            <input type="radio" name="salary_type" value="Fixed" @checked($salaryType === 'Fixed')> Fixed
                        </label>
                        <label class="radio-inline" style="margin-top: -10px;">
                            <input type="radio" name="salary_type" value="Rated" @checked($salaryType === 'Rated')> Rated
                        </label>
                        <label class="radio-inline" style="margin-right:10px; margin-top:-10px;">
                            <input type="radio" name="salary_type" value="Daily" @checked($salaryType === 'Daily')> Fixed Daily
                        </label>
                    </div>
                    <span style="display: block; font-family: Arial, sans-serif; font-size: 14px; color: #333;">
                        <strong>Shift:</strong>
                        {{ !empty($employeeDetails->startshift) ? date('h:i A', strtotime($employeeDetails->startshift)) : '-' }}
                        -
                        {{ !empty($employeeDetails->endshift) ? date('h:i A', strtotime($employeeDetails->endshift)) : '-' }}
                    </span>
                    <span style="display: block; font-family: Arial, sans-serif; font-size: 14px; color: #333;">
                        <strong>Location:</strong> {{ $employeeDetails->location ?? '-' }}
                    </span>
                </div>
                <div style="margin-left: auto; float: right; margin-right: 475px;">
                    <span style="display: block; font-family: Arial, sans-serif; font-size: 14px; color: #333;">
                        <strong>Rate per Day:</strong> Php {{ number_format($salaryRate ?? 0, 2) }}
                    </span>
                    <span style="display: block; font-family: Arial, sans-serif; font-size: 14px; color: #333;">
                        <strong>Effective:</strong> {{ $effectivity ? date('M d, Y', strtotime($effectivity)) : 'N/A' }}
                    </span>
                    <span style="display: block; font-family: Arial, sans-serif; font-size: 14px; color: #FF0000;">
                        <strong>Previous Rate:</strong> Php {{ number_format($prevSalary ?? 0, 2) }}
                    </span>
                </div>
            </div>

            <script>
                let isSaved = {{ $isSaved ? 'true' : 'false' }};
                const saveBtn = document.querySelector('[name="submitPayroll"]');
                if (saveBtn) { saveBtn.addEventListener('click', () => { isSaved = true; }); }
                const backLink = document.getElementById('backLink');
                if (backLink) {
                    backLink.addEventListener('click', function (e) {
                        if (!isSaved) {
                            if (!confirm("You didn't save changes. Are you sure you want to leave?")) e.preventDefault();
                        }
                    });
                }
            </script>

            <div class="panel-body">
                {{-- The per-day attendance/calculation table is being ported in a follow-up pass
                     (base pay/OT, then night differential, then holidays, then leave types).
                     Meanwhile this shows the period's saved totals, if any. --}}
                <table class="table table-bordered-bottom-only">
                    <thead>
                        <tr>
                            <th class="table-head-1">Date</th>
                            <th class="table-head-1">Time In</th>
                            <th class="table-head-1">Time Out</th>
                            <th class="table-head-1">Time In</th>
                            <th class="table-head-1">Time Out</th>
                            <th class="table-head-1">Total Hrs</th>
                            <th class="table-head-1">Reg Hrs</th>
                            <th class="table-head-1">OT</th>
                            <th class="table-head-1">ND</th>
                            <th class="table-head-2">Rate/Day</th>
                            <th class="table-head-2">Reg Days OT Rate</th>
                            <th class="table-head-2">ND Rate</th>
                            <th class="table-head-2">Special Non Working Holiday</th>
                            <th class="table-head-2">OT Special Holiday</th>
                            <th class="table-head-2">OT Regular Holiday</th>
                            <th class="table-head-2">Regular Holidays</th>
                            <th class="table-head-2">Total Pay</th>
                            <th class="table-head-1">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            {{-- populated once the calculation engine is ported --}}
                        @empty
                            <tr>
                                <td colspan="18" class="text-center" style="padding: 20px; color: #7f8c8d;">
                                    <i class="fa fa-info-circle"></i> Day-by-day attendance calculation isn't ported yet — showing saved totals below only.
                                </td>
                            </tr>
                        @endforelse
                        <tr>
                            <td colspan="5" class="table-cont-2" style="font-size: 1.3rem"><strong>TOTAL</strong></td>
                            <td class="table-cont-1" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalhours'], 2) }}</strong></td>
                            <td class="table-cont-1" style="font-size: 1.3rem"><strong>{{ number_format($totals['regularhours'], 2) }}</strong></td>
                            <td class="table-cont-1" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalovertime'], 2) }}</strong></td>
                            <td class="table-cont-1" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalndhrs'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalbasesalary'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalregdaysot'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalndrate'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalspholiday'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalspholidayot'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalregholidayot'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['totalregholiday'], 2) }}</strong></td>
                            <td class="table-cont-2" style="font-size: 1.3rem"><strong>{{ number_format($totals['grandtotal'] ?: $totals['totalpay'], 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>

                <input type="submit" name="submitPayroll" class="btn btn-primary" value="Save Details" style="float:right;">

                {{-- Every total the legacy page tracked, carried through as hidden inputs
                     so save() has a stable field set to persist once the calculation
                     engine is wired up. --}}
                @foreach ($totals as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endforeach
            </div>
        </div>
    </form>
</div>

{{-- ================= DEDUCTIONS ================= --}}
<div class="content-panel deduction-layout">
        <div class="panel-heading">
        <span class="employee-name"><i class="fa fa-file-text"></i> Deduction Management</span>
        <span style="margin-left: auto; font-size: 14px; color: rgba(255,255,255,0.9);">
                <i class="fa fa-user"></i>
                <strong>{{ $employee->lastname ?? '' }}, {{ $employee->firstname ?? '' }} {{ $employee->suffix ?? '' }}</strong>
            </span>
        </div>

    <div class="panel-body">
            <div class="row">
                {{-- LEFT: payroll-specific deductions --}}
                <div class="col-md-6">
                <div class="deduction-card">
                    <div class="card-head card-head--blue">
                        <h4><i class="fa fa-calendar"></i> Payroll-Specific Deductions</h4>
                        <p>For this payroll period only</p>
                        </div>
                    <div class="card-body">
                            <form method="POST" action="{{ route('payroll.edit.deductions.store', ['payroll' => $payroll->id, 'idno' => $idno, 'company' => $company, 'dept' => $deptId]) }}" class="form-horizontal">
                                @csrf
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Description</label>
                                    <div class="col-sm-8">
                                    <input type="text" class="form-control" name="description" placeholder="e.g., Uniform, Cash Advance" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Amount</label>
                                    <div class="col-sm-5" style="padding-right: 5px;">
                                        <div class="input-group">
                                        <span class="input-group-addon">₱</span>
                                        <input type="number" step="0.01" class="form-control" name="amount" style="text-align:right;" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-3" style="padding-left: 0;">
                                    <button type="submit" class="btn btn-block btn-card-primary">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </form>

                        <hr class="card-divider">
                        <h5><i class="fa fa-list"></i> Current Payroll Deductions</h5>
                        <div class="mini-table-wrap">
                            <table class="mini-table">
                                    <colgroup><col style="width: 55%;"><col style="width: 25%;"><col style="width: 20%;"></colgroup>
                                <thead>
                                    <tr><th>Description</th><th>Amount</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($deductions['payroll'] as $ded)
                                            <tr>
                                            <td>{{ $ded->description }}</td>
                                            <td><strong style="color: #3498db;">₱{{ number_format($ded->amount, 2) }}</strong></td>
                                            <td>
                                                    <form method="POST" action="{{ route('payroll.edit.deductions.destroy', ['payroll' => $payroll->id, 'idno' => $idno, 'payrollDeduction' => $ded->id, 'company' => $company, 'dept' => $deptId]) }}" style="display:inline" onsubmit="return confirm('Remove this deduction from current payroll?')">
                                                        @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                        <tr><td colspan="3" class="empty-cell"><i class="fa fa-info-circle"></i> No payroll-specific deductions</td></tr>
                                        @endforelse
                                        @if ($deductions['payroll']->isNotEmpty())
                                        <tr class="row-total">
                                            <td style="text-align: right;">TOTAL:</td>
                                            <td><span style="color: #3498db;">₱{{ number_format($deductions['total'], 2) }}</span></td>
                                            <td></td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: constant deductions --}}
                <div class="col-md-6">
                <div class="deduction-card">
                    <div class="card-head card-head--navy">
                        <h4><i class="fa fa-refresh"></i> Constant Deductions</h4>
                        <p>Apply to all payroll periods</p>
                        </div>
                    <div class="card-body">
                            <form method="POST" action="{{ route('payroll.edit.deductions.constant.store', ['payroll' => $payroll->id, 'idno' => $idno, 'company' => $company, 'dept' => $deptId]) }}" class="form-horizontal">
                                @csrf
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Select Deduction</label>
                                    <div class="col-sm-8">
                                    <select name="deduction_id" class="form-control" required>
                                            <option value="">-- Select from Master List --</option>
                                            @foreach ($deductions['available'] as $master)
                                                <option value="{{ $master->id }}">{{ $master->deduction }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Amount</label>
                                    <div class="col-sm-5" style="padding-right: 5px;">
                                        <div class="input-group">
                                        <span class="input-group-addon">₱</span>
                                        <input type="number" step="0.01" class="form-control" name="amount" style="text-align:right;" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-3" style="padding-left: 0;">
                                    <button type="submit" class="btn btn-block btn-card-navy">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </form>

                        <hr class="card-divider">

                        <div class="info-strip">
                            <span><i class="fa fa-info-circle"></i> Generate all constants to current payroll</span>
                                        <form method="POST" action="{{ route('payroll.edit.deductions.generate', ['payroll' => $payroll->id, 'idno' => $idno, 'company' => $company, 'dept' => $deptId]) }}" onsubmit="return confirm('Generate ALL constant deductions to current payroll?')">
                                            @csrf
                                <button type="submit" class="btn-generate"><i class="fa fa-refresh"></i> Generate</button>
                                        </form>
                            </div>

                        <h5><i class="fa fa-list"></i> Active Constant Deductions</h5>
                        <div class="mini-table-wrap">
                            <table class="mini-table">
                                    <colgroup><col style="width: 45%;"><col style="width: 25%;"><col style="width: 30%;"></colgroup>
                                <thead>
                                    <tr><th>Deduction</th><th>Amount</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($deductions['constant'] as $const)
                                            <tr>
                                            <td>{{ $const->description }}</td>
                                            <td><strong>₱{{ number_format($const->amount, 2) }}</strong></td>
                                            <td>
                                                    <form method="POST" action="{{ route('payroll.edit.deductions.constant.apply', ['payroll' => $payroll->id, 'idno' => $idno, 'employeeDeduction' => $const->id, 'company' => $company, 'dept' => $deptId]) }}" style="display:inline">
                                                        @csrf
                                                    <button type="submit" class="btn btn-xs" style="background: #3498db; color: #fff; margin-right: 3px;" title="Add to current payroll only"><i class="fa fa-plus"></i> Add</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('payroll.edit.deductions.constant.destroy', ['payroll' => $payroll->id, 'idno' => $idno, 'employeeDeduction' => $const->id, 'company' => $company, 'dept' => $deptId]) }}" style="display:inline" onsubmit="return confirm('Remove this constant deduction?')">
                                                        @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                        <tr><td colspan="3" class="empty-cell"><i class="fa fa-info-circle"></i> No constant deductions set</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                        <div class="card-help">
                                <i class="fa fa-info-circle"></i> {{ $deductions['constantInPayroll'] }} constant deduction(s) already in current payroll
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= ADDONS ================= --}}
<div class="content-panel addon-layout">
        <div class="panel-heading">
        <span class="employee-name"><i class="fa fa-file-text"></i> Addons Management</span>
        <span style="margin-left: auto; font-size: 14px; color: rgba(255,255,255,0.9);">
                <i class="fa fa-user"></i>
                <strong>{{ $employee->lastname ?? '' }}, {{ $employee->firstname ?? '' }} {{ $employee->suffix ?? '' }}</strong>
            </span>
        </div>

    <div class="panel-body">
            <div class="row">
                {{-- LEFT: payroll-specific addons --}}
                <div class="col-md-6">
                <div class="addon-card">
                    <div class="card-head card-head--blue">
                        <h4><i class="fa fa-calendar"></i> Payroll-Specific Addons</h4>
                        <p>For this payroll period only</p>
                        </div>
                    <div class="card-body">
                            <form method="POST" action="{{ route('payroll.edit.addons.store', ['payroll' => $payroll->id, 'idno' => $idno, 'company' => $company, 'dept' => $deptId]) }}" class="form-horizontal">
                                @csrf
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Description</label>
                                    <div class="col-sm-8">
                                    <input type="text" class="form-control" name="description" placeholder="e.g., Allowance, Incentives" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Amount</label>
                                    <div class="col-sm-5" style="padding-right: 5px;">
                                        <div class="input-group">
                                        <span class="input-group-addon">₱</span>
                                        <input type="number" step="0.01" class="form-control" name="amount" style="text-align:right;" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-3" style="padding-left: 0;">
                                    <button type="submit" class="btn btn-block btn-card-primary">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </form>

                        <hr class="card-divider">
                        <h5><i class="fa fa-list"></i> Current Payroll Addons</h5>
                        <div class="mini-table-wrap">
                            <table class="mini-table">
                                    <colgroup><col style="width: 55%;"><col style="width: 25%;"><col style="width: 20%;"></colgroup>
                                <thead>
                                    <tr><th>Description</th><th>Amount</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($addons['payroll'] as $add)
                                            <tr>
                                            <td>{{ $add->description }}</td>
                                            <td><strong style="color: #3498db;">₱{{ number_format($add->amount, 2) }}</strong></td>
                                            <td>
                                                    <form method="POST" action="{{ route('payroll.edit.addons.destroy', ['payroll' => $payroll->id, 'idno' => $idno, 'payrollAddon' => $add->id, 'company' => $company, 'dept' => $deptId]) }}" style="display:inline" onsubmit="return confirm('Remove this addon from current payroll?')">
                                                        @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                        <tr><td colspan="3" class="empty-cell"><i class="fa fa-info-circle"></i> No payroll-specific addons</td></tr>
                                        @endforelse
                                        @if ($addons['payroll']->isNotEmpty())
                                        <tr class="row-total">
                                            <td style="text-align: right;">TOTAL:</td>
                                            <td><span style="color: #3498db;">₱{{ number_format($addons['total'], 2) }}</span></td>
                                            <td></td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: constant addons --}}
                <div class="col-md-6">
                <div class="addon-card">
                    <div class="card-head card-head--navy">
                        <h4><i class="fa fa-refresh"></i> Constant Addons</h4>
                        <p>Apply to all payroll periods</p>
                        </div>
                    <div class="card-body">
                            <form method="POST" action="{{ route('payroll.edit.addons.constant.store', ['payroll' => $payroll->id, 'idno' => $idno, 'company' => $company, 'dept' => $deptId]) }}" class="form-horizontal">
                                @csrf
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Select Addons</label>
                                    <div class="col-sm-8">
                                    <select name="addon_id" class="form-control" required>
                                            <option value="">-- Select from Master List --</option>
                                            @foreach ($addons['available'] as $master)
                                                <option value="{{ $master->id }}">{{ $master->addons }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                <label class="col-sm-4 control-label">Amount</label>
                                    <div class="col-sm-5" style="padding-right: 5px;">
                                        <div class="input-group">
                                        <span class="input-group-addon">₱</span>
                                        <input type="number" step="0.01" class="form-control" name="amount" style="text-align:right;" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-3" style="padding-left: 0;">
                                    <button type="submit" class="btn btn-block btn-card-navy">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </form>

                        <hr class="card-divider">

                        <div class="info-strip">
                            <span><i class="fa fa-info-circle"></i> Generate all constants to current payroll</span>
                                        <form method="POST" action="{{ route('payroll.edit.addons.generate', ['payroll' => $payroll->id, 'idno' => $idno, 'company' => $company, 'dept' => $deptId]) }}" onsubmit="return confirm('Generate ALL constant addons to current payroll?')">
                                            @csrf
                                <button type="submit" class="btn-generate"><i class="fa fa-refresh"></i> Generate</button>
                                        </form>
                            </div>

                        <h5><i class="fa fa-list"></i> Active Constant Addons</h5>
                        <div class="mini-table-wrap">
                            <table class="mini-table">
                                    <colgroup><col style="width: 45%;"><col style="width: 25%;"><col style="width: 30%;"></colgroup>
                                <thead>
                                    <tr><th>Addons</th><th>Amount</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($addons['constant'] as $const)
                                            <tr>
                                            <td>{{ $const->description }}</td>
                                            <td><strong>₱{{ number_format($const->amount, 2) }}</strong></td>
                                            <td>
                                                    <form method="POST" action="{{ route('payroll.edit.addons.constant.apply', ['payroll' => $payroll->id, 'idno' => $idno, 'employeeAddon' => $const->id, 'company' => $company, 'dept' => $deptId]) }}" style="display:inline">
                                                        @csrf
                                                    <button type="submit" class="btn btn-xs" style="background: #3498db; color: #fff; margin-right: 3px;" title="Add to current payroll only"><i class="fa fa-plus"></i> Add</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('payroll.edit.addons.constant.destroy', ['payroll' => $payroll->id, 'idno' => $idno, 'employeeAddon' => $const->id, 'company' => $company, 'dept' => $deptId]) }}" style="display:inline" onsubmit="return confirm('Remove this constant addon?')">
                                                        @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                        <tr><td colspan="3" class="empty-cell"><i class="fa fa-info-circle"></i> No constant addons set</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                        <div class="card-help">
                                <i class="fa fa-info-circle"></i> {{ $addons['constantInPayroll'] }} constant addon(s) already in current payroll
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script>
document.addEventListener('keydown', function (e) {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

    @if ($prevId)
        if (e.key === '[') {
            window.location.href = "{{ route('payroll.edit.show', ['payroll' => $payroll->id, 'idno' => $prevId, 'company' => $company, 'dept' => $deptId]) }}";
        }
    @endif
    @if ($nextId)
        if (e.key === ']') {
            window.location.href = "{{ route('payroll.edit.show', ['payroll' => $payroll->id, 'idno' => $nextId, 'company' => $company, 'dept' => $deptId]) }}";
        }
    @endif
});
</script>
@endsection