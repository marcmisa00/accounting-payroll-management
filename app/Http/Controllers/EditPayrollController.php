<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Services\PayrollCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EditPayrollController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    /**
     * Every field attendance_pay_override can hold, in the order they're
     * shown on the override editor. OT and ND live here too now — the same
     * table and the same page handle every manual adjustment for a day.
     */
    private const OVERRIDE_FIELDS = [
        'ot_minutes', 'ndhrs', 'totalwo', 'reghrs', 'ratday', 'regdaysot',
        'ndrate', 'spholiday', 'spholidayot', 'regholidayot', 'regholiday', 'totalpay',
    ];

    public function __construct(private readonly PayrollCalculationService $calculator)
    {
    }

    /**
     * The Edit Payroll screen for one employee within one payroll period.
     * (was: editpayroll.php, the default view with no ?deduction/?addons/?benefits)
     */
    public function show(Request $request, Payroll $payroll, string $idno): View
    {
        [$company, $deptId] = $this->context($request);
        $conn = DB::connection(self::HRIS_CONNECTION);

        $employee = $conn->table('employee_profile')->where('idno', $idno)->first();
        $employeeDetails = $conn->table('employee_details')->where('idno', $idno)->first();

        $designation = $employeeDetails->designation ?? 0;
        $department  = $employeeDetails->department ?? 0;
        $blockOtBefore = ($designation == 71) || ($designation == 46 && $department == 1);

        $payrollDetail = $conn->table('payroll_details')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->first();
        $isSaved   = (bool) $payrollDetail;
        $payrollId = $payrollDetail->id ?? null;

        $employeePayroll = $conn->table('employee_payroll')->where('idno', $idno)->first();
        $salaryType   = $employeePayroll->salary_type ?? 'Rated';
        $prevSalary   = $employeePayroll->previous_salary ?? null;
        $effectivity  = $employeePayroll->effective_date ?? null;
        $salaryRate   = $employeePayroll->salary ?? null;

        [$prevId, $nextId] = $this->findNeighbours($conn, $idno, $company, $department);

        $blEligible = $this->isBereavementLeaveEligible($employeeDetails->dateofhired ?? null, $payroll->periodto);

        $calculation = $this->calculator->calculate(
            $idno,
            $payroll->id,
            (string) $payroll->periodfrom,
            (string) $payroll->periodto,
            $company,
            $salaryType,
            (int) $payroll->days,
            $blockOtBefore,
            $blEligible
        );

        return view('payroll.edit', [
            'payroll'         => $payroll,
            'idno'            => $idno,
            'company'         => $company,
            'deptId'          => $deptId,
            'employee'        => $employee,
            'employeeDetails' => $employeeDetails,
            'isSaved'         => $isSaved,
            'payrollId'       => $payrollId,
            'salaryType'      => $salaryType,
            'prevSalary'      => $prevSalary,
            'effectivity'     => $effectivity,
            'salaryRate'      => $salaryRate,
            'prevId'          => $prevId,
            'nextId'          => $nextId,
            'rows'            => $calculation['rows'],
            'totals'          => $calculation['totals'],
            'deductions'      => $this->deductionPanelData($conn, $idno, $payroll->id),
            'addons'          => $this->addonPanelData($conn, $idno, $payroll->id),
        ]);
    }

    // =====================================================================
    // Deductions
    // =====================================================================

    /**
     * Add a one-time (this-period-only) deduction.
     * (was: submitPayrollDeduction)
     */
    public function storeDeduction(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric'],
        ]);

        $conn = DB::connection(self::HRIS_CONNECTION);

        $exists = $conn->table('payroll_deductions')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->where('description', $validated['description'])
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)
                ->with('error', 'Deduction already exists in this payroll period!');
        }

        $conn->table('payroll_deductions')->insert([
            'idno'          => $idno,
            'payrollperiod' => $payroll->id,
            'description'   => $validated['description'],
            'amount'        => $validated['amount'],
        ]);

        $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'INSERT', $validated['description'], 0, $validated['amount'], $request);

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'One-time deduction added to payroll!');
    }

    /**
     * Add a standing (constant) deduction for this employee, applies to future periods too.
     * (was: addEmployeeDeduction)
     */
    public function addConstantDeduction(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'deduction_id' => ['required'],
            'amount'       => ['required', 'numeric'],
        ]);

        $conn = DB::connection(self::HRIS_CONNECTION);

        $exists = $conn->table('employee_deductions')
            ->where('idno', $idno)
            ->where('deduction_id', $validated['deduction_id'])
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)
                ->with('error', 'This deduction is already a constant deduction for this employee!');
        }

        $conn->table('employee_deductions')->insert([
            'idno'         => $idno,
            'deduction_id' => $validated['deduction_id'],
            'amount'       => $validated['amount'],
        ]);

        $description = $conn->table('deductions')->where('id', $validated['deduction_id'])->value('deduction') ?? 'Deduction';
        $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'constant', 'INSERT', $description, 0, $validated['amount'], $request);

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant deduction added successfully!');
    }

    /**
     * Apply an existing constant deduction to just this payroll period.
     * (was: add_to_current_payrolls)
     */
    public function applyConstantDeduction(Request $request, Payroll $payroll, string $idno, int $employeeDeduction): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $const = $conn->table('employee_deductions as ed')
            ->join('deductions as d', 'ed.deduction_id', '=', 'd.id')
            ->where('ed.id', $employeeDeduction)
            ->where('ed.idno', $idno)
            ->select('ed.amount', 'd.deduction as description')
            ->first();

        if (! $const) {
            return $this->backToEdit($request, $payroll, $idno)->with('error', 'Constant deduction not found.');
        }

        $exists = $conn->table('payroll_deductions')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->where('description', $const->description)
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)->with('error', 'This deduction is already in the current payroll!');
        }

        $conn->table('payroll_deductions')->insert([
            'idno'          => $idno,
            'payrollperiod' => $payroll->id,
            'description'   => $const->description,
            'amount'        => $const->amount,
        ]);

        $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'INSERT', $const->description, 0, $const->amount, $request);

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant deduction added to current payroll!');
    }

    /**
     * Remove a standing constant deduction entirely (all future periods).
     * (was: remove_constant_deduction)
     */
    public function destroyConstantDeduction(Request $request, Payroll $payroll, string $idno, int $employeeDeduction): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $old = $conn->table('employee_deductions as ed')
            ->join('deductions as d', 'ed.deduction_id', '=', 'd.id')
            ->where('ed.id', $employeeDeduction)
            ->where('ed.idno', $idno)
            ->select('ed.amount', 'd.deduction as description')
            ->first();

        $conn->table('employee_deductions')->where('id', $employeeDeduction)->where('idno', $idno)->delete();

        if ($old) {
            $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'constant', 'DELETE', $old->description, $old->amount, 0, $request);
        }

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant deduction removed successfully!');
    }

    /**
     * Remove a deduction line from just this payroll period.
     * (was: remove_payroll_deduction)
     */
    public function destroyDeduction(Request $request, Payroll $payroll, string $idno, int $payrollDeduction): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $old = $conn->table('payroll_deductions')->where('id', $payrollDeduction)->where('idno', $idno)->first();

        $conn->table('payroll_deductions')->where('id', $payrollDeduction)->where('idno', $idno)->delete();

        if ($old) {
            $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'DELETE', $old->description, $old->amount, 0, $request);
        }

        return $this->backToEdit($request, $payroll, $idno);
    }

    /**
     * Copy every standing constant deduction into this payroll period at once.
     * (was: generate_payroll_deductions)
     */
    public function generateDeductions(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $constants = $conn->table('employee_deductions as ed')
            ->join('deductions as d', 'ed.deduction_id', '=', 'd.id')
            ->where('ed.idno', $idno)
            ->select('ed.amount', 'd.deduction as description')
            ->get();

        $inserted = 0;
        $skipped  = 0;

        foreach ($constants as $const) {
            $exists = $conn->table('payroll_deductions')
                ->where('idno', $idno)
                ->where('payrollperiod', $payroll->id)
                ->where('description', $const->description)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $conn->table('payroll_deductions')->insert([
                'idno'          => $idno,
                'payrollperiod' => $payroll->id,
                'description'   => $const->description,
                'amount'        => $const->amount,
            ]);
            $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'INSERT', $const->description, 0, $const->amount, $request);
            $inserted++;
        }

        return $this->backToEdit($request, $payroll, $idno)
            ->with('success', "Generated {$inserted} deduction(s) for this payroll period. {$skipped} were already existing.");
    }

    // =====================================================================
    // Addons (mirrors the deduction endpoints above)
    // =====================================================================

    public function storeAddon(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric'],
        ]);

        $conn = DB::connection(self::HRIS_CONNECTION);

        $exists = $conn->table('payroll_addons')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->where('description', $validated['description'])
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)->with('error', 'Addon already exists in this payroll period!');
        }

        $conn->table('payroll_addons')->insert([
            'idno'          => $idno,
            'payrollperiod' => $payroll->id,
            'description'   => $validated['description'],
            'amount'        => $validated['amount'],
        ]);

        $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'payroll', 'INSERT', $validated['description'], 0, $validated['amount'], $request);

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'One-time addon added to payroll!');
    }

    public function addConstantAddon(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'addon_id' => ['required'],
            'amount'   => ['required', 'numeric'],
        ]);

        $conn = DB::connection(self::HRIS_CONNECTION);

        $exists = $conn->table('employee_addons')
            ->where('idno', $idno)
            ->where('addon_id', $validated['addon_id'])
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)
                ->with('error', 'This addon is already a constant addon for this employee!');
        }

        $conn->table('employee_addons')->insert([
            'idno'     => $idno,
            'addon_id' => $validated['addon_id'],
            'amount'   => $validated['amount'],
        ]);

        $description = $conn->table('addons')->where('id', $validated['addon_id'])->value('addons') ?? 'Addon';
        $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'constant', 'INSERT', $description, 0, $validated['amount'], $request);

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant addon added successfully!');
    }

    public function applyConstantAddon(Request $request, Payroll $payroll, string $idno, int $employeeAddon): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $const = $conn->table('employee_addons as ea')
            ->join('addons as a', 'ea.addon_id', '=', 'a.id')
            ->where('ea.id', $employeeAddon)
            ->where('ea.idno', $idno)
            ->select('ea.amount', 'a.addons as description')
            ->first();

        if (! $const) {
            return $this->backToEdit($request, $payroll, $idno)->with('error', 'Constant addon not found.');
        }

        $exists = $conn->table('payroll_addons')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->where('description', $const->description)
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)->with('error', 'This addon is already in the current payroll!');
        }

        $conn->table('payroll_addons')->insert([
            'idno'          => $idno,
            'payrollperiod' => $payroll->id,
            'description'   => $const->description,
            'amount'        => $const->amount,
        ]);

        $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'constant', 'INSERT', $const->description, 0, $const->amount, $request);

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant addon added to current payroll!');
    }

    public function destroyConstantAddon(Request $request, Payroll $payroll, string $idno, int $employeeAddon): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $old = $conn->table('employee_addons as ea')
            ->join('addons as a', 'ea.addon_id', '=', 'a.id')
            ->where('ea.id', $employeeAddon)
            ->where('ea.idno', $idno)
            ->select('ea.amount', 'a.addons as description')
            ->first();

        $conn->table('employee_addons')->where('id', $employeeAddon)->where('idno', $idno)->delete();

        if ($old) {
            $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'constant', 'DELETE', $old->description, $old->amount, 0, $request);
        }

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant addon removed successfully!');
    }

    public function destroyAddon(Request $request, Payroll $payroll, string $idno, int $payrollAddon): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $old = $conn->table('payroll_addons')
            ->where('id', $payrollAddon)
            ->where('idno', $idno)
            ->first();

        if ($old) {
            $conn->table('payroll_addons')->where('id', $payrollAddon)->where('idno', $idno)->delete();
            $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'payroll', 'DELETE', $old->description, $old->amount, 0, $request);
        }

        return $this->backToEdit($request, $payroll, $idno);
    }

    public function generateAddons(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $constants = $conn->table('employee_addons as ea')
            ->join('addons as a', 'ea.addon_id', '=', 'a.id')
            ->where('ea.idno', $idno)
            ->select('ea.amount', 'a.addons as description')
            ->get();

        $inserted = 0;
        $skipped  = 0;

        foreach ($constants as $const) {
            $exists = $conn->table('payroll_addons')
                ->where('idno', $idno)
                ->where('payrollperiod', $payroll->id)
                ->where('description', $const->description)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $conn->table('payroll_addons')->insert([
                'idno'          => $idno,
                'payrollperiod' => $payroll->id,
                'description'   => $const->description,
                'amount'        => $const->amount,
            ]);
            $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'payroll', 'INSERT', $const->description, 0, $const->amount, $request);
            $inserted++;
        }

        return $this->backToEdit($request, $payroll, $idno)
            ->with('success', "Generated {$inserted} addon(s) for this payroll period. {$skipped} were already existing.");
    }

    // =====================================================================
    // Attendance row deletion + final save
    // =====================================================================

    /**
     * (was: deletetime)
     */
    public function deleteTime(Request $request, Payroll $payroll, string $idno, int $attendance): RedirectResponse
    {
        DB::connection(self::HRIS_CONNECTION)->table('attendance')->where('id', $attendance)->delete();

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Item successfully removed!');
    }

    /**
     * Show the manual-override editor for one attendance day: every field
     * the calculation engine produced for that day, editable, defaulting
     * to the calculated value. Only fields actually changed get stored.
     */
    public function editTime(Request $request,Payroll $payroll, string $idno, int $attendance): View 
    {
        
        [$company, $deptId] = $this->context($request);

        $conn = DB::connection(self::HRIS_CONNECTION);

        $employeeDetails = $conn->table('employee_details')
            ->where('idno', $idno)
            ->first();

        abort_unless($employeeDetails, 404);

        $employeePayroll = $conn->table('employee_payroll')
            ->where('idno', $idno)
            ->first();

        $salaryType = $employeePayroll->salary_type ?? 'Rated';

        $designation = $employeeDetails->designation ?? 0;
        $department  = $employeeDetails->department ?? 0;

        $blockOtBefore =
            ($designation == 71) ||
            ($designation == 46 && $department == 1);

        $blEligible = $this->isBereavementLeaveEligible(
            $employeeDetails->dateofhired ?? null,
            $payroll->periodto
        );

        $calculation = $this->calculator->calculate(
            $idno,
            $payroll->id,
            (string) $payroll->periodfrom,
            (string) $payroll->periodto,
            $company,
            $salaryType,
            (int) $payroll->days,
            $blockOtBefore,
            $blEligible
        );

        $row = collect($calculation['rows'])
            ->firstWhere('attendance_id', $attendance);

        abort_unless(
            $row,
            404,
            'That attendance day was not found in this payroll period.'
        );

        /*
        * Actual attendance record.
        *
        * Idle belongs to this table.
        */
        $attendanceRecord = $conn->table('attendance')
            ->where('id', $attendance)
            ->first();

        abort_unless($attendanceRecord, 404);

        /*
        * Manual payroll overrides.
        *
        * OT minutes, ND, totalwo, etc. belong here.
        */
        $override = $conn->table('attendance_pay_override')
            ->where('attendance_id', $attendance)
            ->first();

        return view('payroll.edit-time', [
            'payroll'          => $payroll,
            'idno'             => $idno,
            'company'          => $company,
            'deptId'           => $deptId,
            'attendance'       => $attendance,
            'row'              => $row,
            'calculated'       => $row['calculated'],
            'override'         => $override,

            // Actual attendance values
            'attendanceRecord' => $attendanceRecord,
            'idle'             => $attendanceRecord->idle,
            'otTime'           => $attendanceRecord->ottime ?? 0,
        ]);
    }
    public function updateTimeOverride(Request $request, Payroll $payroll, string $idno,int $attendance): RedirectResponse 
    {
        
            $validated = $request->validate(
                collect(self::OVERRIDE_FIELDS)
                    ->mapWithKeys(fn ($f) => [
                        $f => ['nullable', 'numeric']
                    ])
                    ->all()
                + [
                    'idle' => ['nullable', 'numeric'],
                ]
            );

            $conn = DB::connection(self::HRIS_CONNECTION);

            /*
            * ============================================================
            * 1. HANDLE IDLE
            * ============================================================
            *
            * Idle belongs directly to attendance.idle.
            */
            $attendanceRecord = $conn->table('attendance')
                ->where('id', $attendance)
                ->first();

            abort_unless($attendanceRecord, 404);

            $oldIdle = $attendanceRecord->idle;
            $newIdle = $validated['idle'] ?? null;

            /*
            * Only write/log when the value actually changed.
            */
            if ((string) $oldIdle !== (string) $newIdle) {

                $logindate = $attendanceRecord->logindate;

                $this->logAdjustment(
                    $conn,
                    $idno,
                    $payroll->id,
                    'payroll',
                    'attendance',
                    $oldIdle === null
                        ? 'INSERT'
                        : ($newIdle === null ? 'DELETE' : 'UPDATE'),
                    'day ' . date('M d, Y', strtotime($logindate)) . ' — Idle',
                    $oldIdle,
                    $newIdle,
                    $request
                );

                $conn->table('attendance')
                    ->where('id', $attendance)
                    ->update([
                        'idle' => $newIdle,
                    ]);
            }

            /*
            * ============================================================
            * 2. HANDLE EXISTING PAYROLL OVERRIDES
            * ============================================================
            */
            $existing = $conn->table('attendance_pay_override')
                ->where('attendance_id', $attendance)
                ->first();

            $newValues = collect(self::OVERRIDE_FIELDS)
                ->mapWithKeys(fn ($f) => [
                    $f => $validated[$f] ?? null
                ])
                ->all();

            foreach (self::OVERRIDE_FIELDS as $field) {

                $old = $existing->{$field} ?? null;
                $new = $newValues[$field];

                if ((string) $old !== (string) $new) {

                    $this->logAdjustment(
                        $conn,
                        $idno,
                        $payroll->id,
                        'payroll',
                        'attendance_override',
                        $old === null
                            ? 'INSERT'
                            : ($new === null ? 'DELETE' : 'UPDATE'),
                        'day ' .
                            date(
                                'M d, Y',
                                strtotime($attendanceRecord->logindate)
                            ) .
                            " — {$field}",
                        $old,
                        $new,
                        $request
                    );
                }
            }

            /*
            * Save the payroll override values.
            */
            $conn->table('attendance_pay_override')->updateOrInsert(
                ['attendance_id' => $attendance],
                $newValues + [
                    'updated_by' => $request->user()?->name
                        ?? auth()->user()?->name,
                    'updated_at' => now(),
                ]
            );

            return $this->backToEdit(
                $request,
                $payroll,
                $idno
            )->with(
                'success',
                'Edit Time changes saved successfully.'
            );
    }

    /**
     * Drop the override row entirely, reverting the day to fully
     * calculated — logging a DELETE for every field that had a value.
     */
    public function clearTimeOverride(Request $request, Payroll $payroll, string $idno, int $attendance): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $existing = $conn->table('attendance_pay_override')->where('attendance_id', $attendance)->first();

        if ($existing) {
            $logindate = $conn->table('attendance')->where('id', $attendance)->value('logindate');

            foreach (self::OVERRIDE_FIELDS as $field) {
                if ($existing->{$field} !== null) {
                    $this->logAdjustment(
                        $conn, $idno, $payroll->id, 'payroll', 'attendance_override', 'DELETE',
                        "day " . date('M d, Y', strtotime($logindate)) . " — {$field}",
                        $existing->{$field}, null, $request
                    );
                }
            }

            $conn->table('attendance_pay_override')->where('attendance_id', $attendance)->delete();
        }

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Override cleared — that day is back to the calculated values.');
    }

    /**
     * The audit trail of every payroll override, deduction, and addon
     * change for this employee's period — no approval workflow, just a
     * visible history of who changed what and when.
     */
    public function historyIndex(Request $request): View
    {
        [$company, $deptId] = $this->context($request);

        $payrolls = Payroll::query()
            ->orderByDesc('periodfrom')
            ->get();

        return view('payroll.history-index', [
            'payrolls' => $payrolls,
            'company'  => $company,
            'deptId'   => $deptId,
        ]);
    }
    public function history(Request $request, Payroll $payroll): View
    {
            [$company, $deptId] = $this->context($request);

            $employees = DB::connection(self::HRIS_CONNECTION)
                ->table('payroll_adjustment_history as h')
                ->leftJoin(
                    'employee_profile as ep',
                    'ep.idno',
                    '=',
                    'h.idno'
                )
                ->where('h.payrollperiod', (string) $payroll->id)
                ->select(
                    'h.idno',
                    'ep.firstname',
                    'ep.middlename',
                    'ep.lastname'
                )
                ->distinct()
                ->orderBy('ep.lastname')
                ->orderBy('ep.firstname')
                ->get();

            $employee = $request->input('employee');
            $type     = $request->input('type');
            $action   = $request->input('action');

            $hasFilter = !empty($employee)
                || !empty($type)
                || !empty($action);

            $entries = collect();

            if ($hasFilter) {
                $query = DB::connection(self::HRIS_CONNECTION)
                    ->table('payroll_adjustment_history as h')


                    ->leftJoin(
                        'employee_profile as ep',
                        'ep.idno',
                        '=',
                        'h.idno'
                    )
                    ->leftJoin(
                        'employee_profile as cb',
                        'cb.idno',
                        '=',
                        'h.changed_by'
                    )
                    ->where(
                        'h.payrollperiod',
                        (string) $payroll->id
                    )
                    ->select(
                        'h.*',
                        'ep.firstname',
                        'ep.middlename',
                        'ep.lastname',
                        'cb.firstname as changed_by_firstname',
                        'cb.middlename as changed_by_middlename',
                        'cb.lastname as changed_by_lastname'
                    );

               if (!empty($employee)) {
                    $query->where(function ($q) use ($employee) {
                        $q->where('h.idno', 'like', "%{$employee}%")
                        ->orWhere('ep.firstname', 'like', "%{$employee}%")
                        ->orWhere('ep.middlename', 'like', "%{$employee}%")
                        ->orWhere('ep.lastname', 'like', "%{$employee}%");
                    });
                }
                if (!empty($type)) {
                    $query->where('h.type', $type);
                }
                if (!empty($action)) {
                    $query->where('h.action', $action);
                }
                $entries = $query
                    ->orderByDesc('h.changed_at')
                    ->orderByDesc('h.id')
                    ->get();
            }
            return view('payroll.history', [
                'payroll'   => $payroll,
                'company'   => $company,
                'deptId'    => $deptId,
                'employee'  => $employee,
                'type'      => $type,
                'action'    => $action,
                'hasFilter' => $hasFilter,
                'entries'   => $entries,
            ]);
    }


    public function save(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'salary_type' => ['required', 'in:Fixed,Rated,Daily'],
        ] + collect($this->calculator->emptyTotals())->keys()
            ->mapWithKeys(fn ($field) => [$field => ['nullable', 'numeric']])
            ->all());

        $conn = DB::connection(self::HRIS_CONNECTION);
        $now  = now();
        $addedby = $request->user()?->name ?? auth()->user()?->name;

        $totals = collect($this->calculator->emptyTotals())->keys()
            ->mapWithKeys(fn ($field) => [$field => $validated[$field] ?? 0])
            ->all();

        $columns = [
            'reghours'                 => $totals['regular_hours'],
            'reghoursot'               => $totals['totalovertime'],
            'totalspholiday'           => $totals['totalspholiday'],
            'totalregholiday'          => $totals['totalregholiday'],
            'regholidayhrs'            => $totals['regholidayhrs'],
            'spholidayhrs'             => $totals['spholidayhrs'],
            'reghoursotamount'         => $totals['totalregdaysot'],
            'regholidayhrsnotwork'     => $totals['totalhoursnotworked'],
            'regholidayamountnotwork'  => $totals['hoursnotworkedamount'],
            'regholidayhrswork1'       => $totals['regholidaywork1'],
            'regholidayamountwork1'    => $totals['regholidayworkamount1'],
            'regholidayhrswork2'       => $totals['regholidaywork2'],
            'regholidayamountwork2'    => $totals['regholidayworkamount2'],
            'regholidayothrs'          => $totals['regholidayothrs'],
            'regholidayotamount'       => $totals['regholidayotamount'],
            'spholidayhrs1'            => $totals['spholidayhours1'],
            'spholidayamount1'         => $totals['spholidayamount1'],
            'spholidayhrs2'            => $totals['spholidayhours2'],
            'spholidayamount2'         => $totals['spholidayamount2'],
            'spholidayothrs'           => $totals['spholidayothrs'],
            'spholidayotamount'        => $totals['spholidayotamount'],
            'paidsplamount'            => $totals['paidsplamount'],
            'paidsplhrs'               => $totals['paidsplhrs'],
            'ndhrs'                    => $totals['ndhrs'],
            'ndamount'                 => $totals['ndamount'],
            'paidslhrs'                => $totals['paidSLhrs'],
            'paidslamount'             => $totals['paidSLamount'],
            'paidvlhrs'                => $totals['paidVLhrs'],
            'paidvlamount'             => $totals['paidVLamount'],
            'paidptlhrs'               => $totals['paidptlhrs'],
            'paidptlamount'            => $totals['paidptlamount'],
            'paidblhrs'                => $totals['paidBLhrs'],
            'paidblamount'             => $totals['paidBLamount'],
            'bdayleavehrs'             => $totals['bdayleavehrs'],
            'bdayleaveamount'          => $totals['bdayleaveamount'],
            'doubleholidaypay'         => $totals['doubleholidaypay'],
            'totalbasesalary'          => $totals['totalbasesalary'],
            'totalpay'                 => $totals['totalpay'],
            'reghours_prev'            => $totals['reghours_prev'],
            'reghoursot_prev'          => $totals['reghoursot_prev'],
            'reghoursotamount_prev'    => $totals['reghoursotamount_prev'],
            'regholidayhrs_prev'       => $totals['regholidayhrs_prev'],
            'regholidayamount_prev'    => $totals['regholidayamount_prev'],
            'regholidayothrs_prev'     => $totals['regholidayothrs_prev'],
            'regholidayotamount_prev'  => $totals['regholidayotamount_prev'],
            'spholidayhrs_prev'        => $totals['spholidayhrs_prev'],
            'spholidayamount_prev'     => $totals['spholidayamount_prev'],
            'spholidayothrs_prev'      => $totals['spholidayothrs_prev'],
            'spholidayotamount_prev'   => $totals['spholidayotamount_prev'],
            'ndhrs_prev'               => $totals['ndhrs_prev'],
            'ndamount_prev'            => $totals['ndamount_prev'],
            'totalbasesalary_prev'     => $totals['totalbasesalary_prev'],
            'totalpay_prev'            => $totals['totalpay_prev'],
            'paidvlhrs_prev'           => $totals['paidVLhrs_prev'],
            'paidvlamount_prev'        => $totals['paidVLamount_prev'],
            'bdayleavehrs_prev'        => $totals['bdayleavehrs_prev'],
            'bdayleaveamount_prev'     => $totals['bdayleaveamount_prev'],
        ];

        $existing = $conn->table('payroll_details')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->first();

        if ($existing) {
            $conn->table('payroll_details')
                ->where('idno', $idno)
                ->where('payrollperiod', $payroll->id)
                ->update($columns + [
                    'updatedby'       => $addedby,
                    'updateddatetime' => $now,
                ]);
        } else {
            $conn->table('payroll_details')->insert($columns + [
                'idno'          => $idno,
                'payrollperiod' => $payroll->id,
                'addedby'       => $addedby,
                'addeddatetime' => $now,
            ]);
        }

        $employeePayrollExists = $conn->table('employee_payroll')->where('idno', $idno)->exists();

        if ($employeePayrollExists) {
            $conn->table('employee_payroll')->where('idno', $idno)->update(['salary_type' => $validated['salary_type']]);
        } else {
            $conn->table('employee_payroll')->insert(['idno' => $idno, 'salary_type' => $validated['salary_type']]);
        }

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Payroll successfully saved!');
    }

    // =====================================================================
    // Private helpers
    // =====================================================================

    /**
     * Pull company + department (dept) out of the query string, since the
     * edit page is reached from a specific company/department tab but
     * isn't itself scoped to one in the URL path.
     */
    private function context(Request $request): array
    {
        return [$request->query('company', ''), $request->query('dept')];
    }

    private function backToEdit(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        [$company, $deptId] = $this->context($request);

        return redirect()->route('payroll.edit.show', [
            'payroll' => $payroll->id,
            'idno'    => $idno,
            'company' => $company,
            'dept'    => $deptId,
        ]);
    }

    /**
     * Department-aware previous/next employee ids, same ordering rule as
     * the original page (company + department, lastname ASC).
     */
    private function findNeighbours($conn, string $idno, string $company, $department): array
    {
        $employeeList = $conn->table('employee_details as ed')
            ->join('employee_profile as ep', 'ep.idno', '=', 'ed.idno')
            ->where('ed.company', $company)
            ->where('ed.department', $department)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->orderBy('ep.lastname')
            ->pluck('ed.idno');

        $currentIndex = $employeeList->search($idno);

        if ($currentIndex === false) {
            return [null, null];
        }

        return [
            $employeeList->get($currentIndex - 1),
            $employeeList->get($currentIndex + 1),
        ];
    }

    /**
     * Bereavement Leave requires >= 18 months tenure as of the payroll
     * period's end date. Ported as-is from the legacy calculation.
     */
    private function isBereavementLeaveEligible(?string $dateOfHire, $periodEnd): bool
    {
        if (empty($dateOfHire) || $dateOfHire === '0000-00-00') {
            return false;
        }

        $hireDate = new \DateTime($dateOfHire);
        $endDate  = new \DateTime((string) $periodEnd);
        $interval = $hireDate->diff($endDate);
        $tenureMonths = ($interval->y * 12) + $interval->m;

        return $tenureMonths >= 18;
    }

    private function logAdjustment($conn, string $idno, int $periodId, string $type, string $category, string $action, string $description, $oldValue, $newValue, Request $request): void
    {
        $changedBy = optional($request->user())->idno ?? optional($request->user())->id;

        $conn->table('payroll_adjustment_history')->insert([
            'idno'          => $idno,
            'payrollperiod' => $periodId,
            'type'          => $type,
            'category'      => $category,
            'action'        => $action,
            'description'   => $description,
            'old_value'     => $oldValue,
            'new_value'     => $newValue,
            'changed_by'    => $changedBy,
            'changed_at'    => now(),
        ]);
    }

    private function deductionPanelData($conn, string $idno, int $periodId): array
    {
        $payrollDeductions = $conn->table('payroll_deductions')
            ->where('idno', $idno)
            ->where('payrollperiod', $periodId)
            ->orderBy('description')
            ->get();

        $constantDeductions = $conn->table('employee_deductions as ed')
            ->join('deductions as d', 'ed.deduction_id', '=', 'd.id')
            ->where('ed.idno', $idno)
            ->orderBy('d.deduction')
            ->select('ed.id', 'ed.amount', 'd.deduction as description')
            ->get();

        $assignedDeductionIds = $conn->table('employee_deductions')->where('idno', $idno)->pluck('deduction_id');

        $availableMasterDeductions = $conn->table('deductions')
            ->whereNotIn('id', $assignedDeductionIds->isNotEmpty() ? $assignedDeductionIds : [0])
            ->orderBy('deduction')
            ->get();

        $constantDescriptions = $constantDeductions->pluck('description');
        $constantInPayroll = $constantDescriptions->isEmpty() ? 0 : $conn->table('payroll_deductions')
            ->where('idno', $idno)
            ->where('payrollperiod', $periodId)
            ->whereIn('description', $constantDescriptions)
            ->count();

        return [
            'payroll'   => $payrollDeductions,
            'constant'  => $constantDeductions,
            'available' => $availableMasterDeductions,
            'total'     => $payrollDeductions->sum('amount'),
            'constantInPayroll' => $constantInPayroll,
        ];
    }

    private function addonPanelData($conn, string $idno, int $periodId): array
    {
        $payrollAddons = $conn->table('payroll_addons')
            ->where('idno', $idno)
            ->where('payrollperiod', $periodId)
            ->orderBy('description')
            ->get();

        $constantAddons = $conn->table('employee_addons as ea')
            ->join('addons as a', 'ea.addon_id', '=', 'a.id')
            ->where('ea.idno', $idno)
            ->orderBy('a.addons')
            ->select('ea.id', 'ea.amount', 'a.addons as description')
            ->get();

        $assignedAddonIds = $conn->table('employee_addons')->where('idno', $idno)->pluck('addon_id');

        $availableMasterAddons = $conn->table('addons')
            ->whereNotIn('id', $assignedAddonIds->isNotEmpty() ? $assignedAddonIds : [0])
            ->orderBy('addons')
            ->get();

        $constantDescriptions = $constantAddons->pluck('description');
        $constantInPayroll = $constantDescriptions->isEmpty() ? 0 : $conn->table('payroll_addons')
            ->where('idno', $idno)
            ->where('payrollperiod', $periodId)
            ->whereIn('description', $constantDescriptions)
            ->count();

        return [
            'payroll'   => $payrollAddons,
            'constant'  => $constantAddons,
            'available' => $availableMasterAddons,
            'total'     => $payrollAddons->sum('amount'),
            'constantInPayroll' => $constantInPayroll,
        ];
    }
}