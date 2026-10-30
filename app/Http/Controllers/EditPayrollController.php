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

        $department  = $employeeDetails->department ?? 0;

        $payrollDetail = $conn->table('payroll_details')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->first();
        $isSaved   = (bool) $payrollDetail;
        $isPosted  = ($payrollDetail->status ?? null) === 'posted';
        $payrollId = $payrollDetail->id ?? null;

        $employeePayroll = $conn->table('employee_payroll')->where('idno', $idno)->first();
        $salaryType   = $employeePayroll->salary_type ?? 'Rated';
        $prevSalary   = $employeePayroll->previous_salary ?? null;
        $effectivity  = $employeePayroll->effective_date ?? null;
        $salaryRate   = $employeePayroll->salary ?? null;

        [$prevId, $nextId] = $this->findNeighbours($conn, $idno, $company, $department);

        $calculation = $this->calculateFor($conn, $payroll, $idno, $company, $salaryType);

        return view('payroll.edit', [
            'payroll'         => $payroll,
            'idno'            => $idno,
            'company'         => $company,
            'deptId'          => $deptId,
            'employee'        => $employee,
            'employeeDetails' => $employeeDetails,
            'isSaved'         => $isSaved,
            'isPosted'        => $isPosted,
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
            'amount'      => ['required', 'numeric', 'gt:0'],
        ]);

        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

        $exists = $conn->table('payroll_deductions')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->where('description', $validated['description'])
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)
                ->with('error', 'Deduction already exists in this payroll period!');
        }

        $conn->transaction(function () use ($conn, $idno, $payroll, $validated, $request) {
            $conn->table('payroll_deductions')->insert([
                'idno'          => $idno,
                'payrollperiod' => $payroll->id,
                'description'   => $validated['description'],
                'amount'        => $validated['amount'],
            ]);

            $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'INSERT', $validated['description'], 0, $validated['amount'], $request);
        });

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'One-time deduction added to payroll!');
    }

    /**
     * Add a standing (constant) deduction for this employee, applies to future periods too.
     * (was: addEmployeeDeduction)
     */
    public function addConstantDeduction(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'deduction_id' => ['required', 'integer', 'exists:' . self::HRIS_CONNECTION . '.deductions,id'],
            'amount'       => ['required', 'numeric', 'gt:0'],
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

        $conn->transaction(function () use ($conn, $idno, $payroll, $validated, $request) {
            $conn->table('employee_deductions')->insert([
                'idno'         => $idno,
                'deduction_id' => $validated['deduction_id'],
                'amount'       => $validated['amount'],
            ]);

            $description = $conn->table('deductions')->where('id', $validated['deduction_id'])->value('deduction') ?? 'Deduction';
            $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'constant', 'INSERT', $description, 0, $validated['amount'], $request);
        });

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant deduction added successfully!');
    }

    /**
     * Apply an existing constant deduction to just this payroll period.
     * (was: add_to_current_payrolls)
     */
    public function applyConstantDeduction(Request $request, Payroll $payroll, string $idno, int $employeeDeduction): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

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

        $conn->transaction(function () use ($conn, $idno, $payroll, $const, $request) {
            $conn->table('payroll_deductions')->insert([
                'idno'          => $idno,
                'payrollperiod' => $payroll->id,
                'description'   => $const->description,
                'amount'        => $const->amount,
            ]);

            $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'INSERT', $const->description, 0, $const->amount, $request);
        });

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

        $conn->transaction(function () use ($conn, $idno, $payroll, $employeeDeduction, $old, $request) {
            $conn->table('employee_deductions')->where('id', $employeeDeduction)->where('idno', $idno)->delete();

            if ($old) {
                $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'constant', 'DELETE', $old->description, $old->amount, 0, $request);
            }
        });

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant deduction removed successfully!');
    }

    /**
     * Remove a deduction line from just this payroll period.
     * (was: remove_payroll_deduction)
     */
    public function destroyDeduction(Request $request, Payroll $payroll, string $idno, int $payrollDeduction): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

        $old = $conn->table('payroll_deductions')
            ->where('id', $payrollDeduction)
            ->where('idno', $idno)
            ->where('payrollperiod', $payroll->id)
            ->first();

        if ($old) {
            $conn->transaction(function () use ($conn, $idno, $payroll, $old, $request) {
                $conn->table('payroll_deductions')->where('id', $old->id)->delete();
                $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'DELETE', $old->description, $old->amount, 0, $request);
            });
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

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

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

            $conn->transaction(function () use ($conn, $idno, $payroll, $const, $request) {
                $conn->table('payroll_deductions')->insert([
                    'idno'          => $idno,
                    'payrollperiod' => $payroll->id,
                    'description'   => $const->description,
                    'amount'        => $const->amount,
                ]);
                $this->logAdjustment($conn, $idno, $payroll->id, 'deduction', 'payroll', 'INSERT', $const->description, 0, $const->amount, $request);
            });
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
            'amount'      => ['required', 'numeric', 'gt:0'],
        ]);

        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

        $exists = $conn->table('payroll_addons')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->where('description', $validated['description'])
            ->exists();

        if ($exists) {
            return $this->backToEdit($request, $payroll, $idno)->with('error', 'Addon already exists in this payroll period!');
        }

        $conn->transaction(function () use ($conn, $idno, $payroll, $validated, $request) {
            $conn->table('payroll_addons')->insert([
                'idno'          => $idno,
                'payrollperiod' => $payroll->id,
                'description'   => $validated['description'],
                'amount'        => $validated['amount'],
            ]);

            $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'payroll', 'INSERT', $validated['description'], 0, $validated['amount'], $request);
        });

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'One-time addon added to payroll!');
    }

    public function addConstantAddon(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'addon_id' => ['required', 'integer', 'exists:' . self::HRIS_CONNECTION . '.addons,id'],
            'amount'   => ['required', 'numeric', 'gt:0'],
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

        $conn->transaction(function () use ($conn, $idno, $payroll, $validated, $request) {
            $conn->table('employee_addons')->insert([
                'idno'     => $idno,
                'addon_id' => $validated['addon_id'],
                'amount'   => $validated['amount'],
            ]);

            $description = $conn->table('addons')->where('id', $validated['addon_id'])->value('addons') ?? 'Addon';
            $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'constant', 'INSERT', $description, 0, $validated['amount'], $request);
        });

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant addon added successfully!');
    }

    public function applyConstantAddon(Request $request, Payroll $payroll, string $idno, int $employeeAddon): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

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

        // Logged as 'payroll': this adds a line to this period only (same as applyConstantDeduction)
        $conn->transaction(function () use ($conn, $idno, $payroll, $const, $request) {
            $conn->table('payroll_addons')->insert([
                'idno'          => $idno,
                'payrollperiod' => $payroll->id,
                'description'   => $const->description,
                'amount'        => $const->amount,
            ]);

            $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'payroll', 'INSERT', $const->description, 0, $const->amount, $request);
        });

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

        $conn->transaction(function () use ($conn, $idno, $payroll, $employeeAddon, $old, $request) {
            $conn->table('employee_addons')->where('id', $employeeAddon)->where('idno', $idno)->delete();

            if ($old) {
                $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'constant', 'DELETE', $old->description, $old->amount, 0, $request);
            }
        });

        return $this->backToEdit($request, $payroll, $idno)->with('success', 'Constant addon removed successfully!');
    }

    public function destroyAddon(Request $request, Payroll $payroll, string $idno, int $payrollAddon): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

        $old = $conn->table('payroll_addons')
            ->where('id', $payrollAddon)
            ->where('idno', $idno)
            ->where('payrollperiod', $payroll->id)
            ->first();

        if ($old) {
            $conn->transaction(function () use ($conn, $idno, $payroll, $old, $request) {
                $conn->table('payroll_addons')->where('id', $old->id)->delete();
                $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'payroll', 'DELETE', $old->description, $old->amount, 0, $request);
            });
        }

        return $this->backToEdit($request, $payroll, $idno);
    }

    public function generateAddons(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

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

            $conn->transaction(function () use ($conn, $idno, $payroll, $const, $request) {
                $conn->table('payroll_addons')->insert([
                    'idno'          => $idno,
                    'payrollperiod' => $payroll->id,
                    'description'   => $const->description,
                    'amount'        => $const->amount,
                ]);
                $this->logAdjustment($conn, $idno, $payroll->id, 'addon', 'payroll', 'INSERT', $const->description, 0, $const->amount, $request);
            });
            $inserted++;
        }

        return $this->backToEdit($request, $payroll, $idno)
            ->with('success', "Generated {$inserted} addon(s) for this payroll period. {$skipped} were already existing.");
    }

    // =====================================================================
    // Per-day time / pay overrides
    // =====================================================================

    /**
     * Show the manual-override editor for one attendance day: every field
     * the calculation engine produced for that day, editable, defaulting
     * to the calculated value. Only fields actually changed get stored.
     */
    public function editTime(Request $request, Payroll $payroll, string $idno, int $attendance): View
    {
        [$company, $deptId] = $this->context($request);

        $conn = DB::connection(self::HRIS_CONNECTION);

        $employeeDetails = $conn->table('employee_details')
            ->where('idno', $idno)
            ->first();

        abort_unless($employeeDetails, 404);

        $calculation = $this->calculateFor($conn, $payroll, $idno, $company);

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
            ->where('idno', $idno)
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
            'isPosted'         => $this->isPosted($conn, $payroll, $idno),

            // Actual attendance values
            'attendanceRecord' => $attendanceRecord,
            'idle'             => $attendanceRecord->idle,
            'otTime'           => $attendanceRecord->ottime ?? 0,
        ]);
    }

    private function minutesToTime($minutes): ?string
    {
        if ($minutes === null || $minutes === '') {
            return null;
        }

        $minutes = (int) $minutes;

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d:00', $hours, $mins);
    }

    private function timeToMinutes($time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        $parts = array_map('intval', explode(':', (string) $time));

        return (($parts[0] ?? 0) * 60) + ($parts[1] ?? 0);
    }

    public function updateTimeOverride(Request $request, Payroll $payroll, string $idno, int $attendance): RedirectResponse
    {
        $validated = $request->validate(
            collect(self::OVERRIDE_FIELDS)
                ->mapWithKeys(fn ($f) => [
                    $f => ['nullable', 'numeric']
                ])
                ->all()
            + [
                'idle' => ['nullable', 'integer', 'min:0'],
            ]
        );

        [$company] = $this->context($request);
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

        // Only this employee's own days in this period can be overridden.
        $attendanceRecord = $this->attendanceInPeriod($conn, $payroll, $idno, $attendance, $company);

        abort_unless($attendanceRecord, 404, 'That attendance day was not found in this payroll period.');

        $dayLabel = 'day ' . date('M d, Y', strtotime($attendanceRecord->logindate));

        $existing = $conn->table('attendance_pay_override')
            ->where('attendance_id', $attendance)
            ->first();

        $newValues = collect(self::OVERRIDE_FIELDS)
            ->mapWithKeys(fn ($f) => [
                $f => $validated[$f] ?? null
            ])
            ->all();

        $conn->transaction(function () use ($conn, $request, $payroll, $idno, $attendance, $attendanceRecord, $existing, $newValues, $validated, $dayLabel) {
            /*
            * ============================================================
            * 1. HANDLE IDLE (attendance.idle)
            * ============================================================
            *
            * NULL and 00:00:00 both mean "no idle" to the engine, so they
            * are compared as minutes — saving the form untouched no longer
            * turns NULL into 00:00:00 or logs a change.
            */
            $oldIdleMinutes = $this->timeToMinutes($attendanceRecord->idle) ?? 0;
            $newIdleMinutes = (int) ($validated['idle'] ?? 0);

            if ($oldIdleMinutes !== $newIdleMinutes) {
                $this->logAdjustment(
                    $conn,
                    $idno,
                    $payroll->id,
                    'payroll',
                    'attendance',
                    $oldIdleMinutes === 0
                        ? 'INSERT'
                        : ($newIdleMinutes === 0 ? 'DELETE' : 'UPDATE'),
                    $dayLabel . ' — Idle',
                    $oldIdleMinutes,
                    $newIdleMinutes,
                    $request
                );

                $conn->table('attendance')
                    ->where('id', $attendance)
                    ->update([
                        'idle' => $this->minutesToTime($newIdleMinutes),
                    ]);
            }

            /*
            * ============================================================
            * 2. HANDLE PAYROLL OVERRIDES (attendance_pay_override)
            * ============================================================
            */
            foreach (self::OVERRIDE_FIELDS as $field) {
                $old = $existing->{$field} ?? null;
                $new = $newValues[$field];

                if (! $this->sameValue($old, $new)) {
                    $this->logAdjustment(
                        $conn,
                        $idno,
                        $payroll->id,
                        'payroll',
                        'attendance_override',
                        $old === null
                            ? 'INSERT'
                            : ($new === null ? 'DELETE' : 'UPDATE'),
                        "{$dayLabel} — {$field}",
                        $old,
                        $new,
                        $request
                    );
                }
            }

            $hasAnyOverride = collect($newValues)->contains(fn ($v) => $v !== null);

            if (! $hasAnyOverride) {
                // Every field blank: no override row at all, the day is fully calculated.
                $conn->table('attendance_pay_override')
                    ->where('attendance_id', $attendance)
                    ->delete();

                return;
            }

            $updatedBy = session('portal_idno')
                ?? session('accounting_idno')
                ?? $request->user()?->idno
                ?? $request->user()?->id;

            $conn->table('attendance_pay_override')->updateOrInsert(
                ['attendance_id' => $attendance],
                $newValues + [
                    'updated_by' => $updatedBy,
                    'updated_at' => now(),
                ]
            );
        });

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
        [$company] = $this->context($request);
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

        $attendanceRecord = $this->attendanceInPeriod($conn, $payroll, $idno, $attendance, $company);

        abort_unless($attendanceRecord, 404, 'That attendance day was not found in this payroll period.');

        $dayLabel = 'day ' . date('M d, Y', strtotime($attendanceRecord->logindate));

        $existing = $conn->table('attendance_pay_override')
            ->where('attendance_id', $attendance)
            ->first();

        $conn->transaction(function () use ($conn, $request, $payroll, $idno, $attendance, $attendanceRecord, $existing, $dayLabel) {
            // Clear Idle override
            $oldIdleMinutes = $this->timeToMinutes($attendanceRecord->idle) ?? 0;

            if ($oldIdleMinutes > 0) {
                $this->logAdjustment(
                    $conn,
                    $idno,
                    $payroll->id,
                    'payroll',
                    'attendance',
                    'DELETE',
                    $dayLabel . ' — Idle',
                    $oldIdleMinutes,
                    null,
                    $request
                );
            }

            if ($attendanceRecord->idle !== null) {
                $conn->table('attendance')
                    ->where('id', $attendance)
                    ->update([
                        'idle' => null,
                    ]);
            }

            // Clear payroll field overrides
            if ($existing) {
                foreach (self::OVERRIDE_FIELDS as $field) {
                    if ($existing->{$field} !== null) {
                        $this->logAdjustment(
                            $conn,
                            $idno,
                            $payroll->id,
                            'payroll',
                            'attendance_override',
                            'DELETE',
                            "{$dayLabel} — {$field}",
                            $existing->{$field},
                            null,
                            $request
                        );
                    }
                }

                $conn->table('attendance_pay_override')
                    ->where('attendance_id', $attendance)
                    ->delete();
            }
        });

        return $this->backToEdit(
            $request,
            $payroll,
            $idno
        )->with(
            'success',
            'Override cleared — that day is back to the calculated values.'
        );
    }
    /**
     * The audit trail of every payroll override, deduction, and addon
     * change for this employee's period — no approval workflow, just a
     * visible history of who changed what and when.
     */
    
    public function historyIndex(Request $request)
        {
            [$company, $deptId] = $this->context($request);

            $payroll = Payroll::query()
                ->orderByDesc('periodfrom')
                ->orderByDesc('id')
                ->first();

            if (!$payroll) {
                return view('payroll.history-index', [
                    'payrolls' => collect(),
                    'company'  => $company,
                    'deptId'   => $deptId,
                ]);
            }

            return redirect()->route('payroll.history', [
                'payroll' => $payroll->id,
                'company' => $company,
                'dept'    => $deptId,
            ]);
        }

    public function historySelect(Request $request)
        {
            $request->validate([
                'period' => 'required|integer',
            ]);

            return redirect()->route('payroll.history', [
                'payroll' => $request->period,
            ]);
        }
    
    public function history(Request $request, Payroll $payroll): View
        {
            [$company, $deptId] = $this->context($request);

            $v = $request->validate([
                'period'      => ['nullable', 'integer'],
                'employee'    => ['nullable', 'string', 'max:100'],
                'record_type' => ['nullable', 'in:payroll-ADD,payroll-DED,EDIT TIME'],
                'user'        => ['nullable', 'string', 'max:50'],
                'from'        => ['nullable', 'date_format:Y-m-d'],
                'to'          => ['nullable', 'date_format:Y-m-d'],
            ]);

            $payroll = Payroll::query()->findOrFail($v['period'] ?? $payroll->id);

            $payrolls = Payroll::query()
                ->orderByDesc('periodfrom')
                ->orderByDesc('id')
                ->get();

            $conn = DB::connection(self::HRIS_CONNECTION);

            // People who have made changes, for the "Changed by" filter.
            $users = $conn->table('payroll_adjustment_history as h')
                ->leftJoin('employee_profile as cb', 'cb.idno', '=', 'h.changed_by')
                ->whereNotNull('h.changed_by')
                ->where('h.changed_by', '!=', '')
                ->select('h.changed_by', 'cb.firstname', 'cb.lastname')
                ->distinct()
                ->get()
                ->unique('changed_by')
                ->map(fn ($u) => (object) [
                    'idno' => $u->changed_by,
                    'name' => trim(($u->lastname ?? '') . ', ' . ($u->firstname ?? ''), ', ') ?: $u->changed_by,
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $employee = trim((string) ($v['employee'] ?? ''));
            $recordType = $v['record_type'] ?? '';
            $user = $v['user'] ?? '';
            $from = $v['from'] ?? '';
            $to = $v['to'] ?? '';

            // Search all periods only when an employee is entered.
            $searchAllPeriods = $employee !== '';

            $query = $conn
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
                );

            // Default: show all entries from the selected payroll period.
            // Employee search: show matching entries from all periods.
            if (!$searchAllPeriods) {
                $query->where('h.payrollperiod', (string) $payroll->id);
            }

            if ($employee !== '') {
                $s = '%' . addcslashes($employee, '%_\\') . '%';
                $query->where(function ($q) use ($s) {
                    $q->where('h.idno', 'like', $s)
                        ->orWhere('ep.firstname', 'like', $s)
                        ->orWhere('ep.middlename', 'like', $s)
                        ->orWhere('ep.lastname', 'like', $s)
                        ->orWhereRaw("CONCAT_WS(' ', ep.firstname, ep.lastname) LIKE ?", [$s])
                        ->orWhereRaw("CONCAT_WS(' ', ep.lastname, ep.firstname) LIKE ?", [$s]);
                });
            }

            $query->when($user !== '', fn ($q) => $q->where('h.changed_by', $user))
                ->when($from !== '', fn ($q) => $q->where('h.changed_at', '>=', $from . ' 00:00:00'))
                ->when($to !== '', fn ($q) => $q->where('h.changed_at', '<=', $to . ' 23:59:59'));

            // One combined filter for Add-on, Deduction, and Edit Time.
            $typeMap = [
                'payroll-ADD' => 'addon',
                'payroll-DED' => 'deduction',
                'EDIT TIME'   => 'payroll',
            ];

            if (isset($typeMap[$recordType])) {
                $query->where('h.type', $typeMap[$recordType]);
            }

            $entries = $query
                ->select(
                    'h.*',
                    'ep.firstname',
                    'ep.middlename',
                    'ep.lastname',
                    'cb.firstname as changed_by_firstname',
                    'cb.middlename as changed_by_middlename',
                    'cb.lastname as changed_by_lastname'
                )
                ->orderByDesc('h.changed_at')
                ->orderByDesc('h.id')
                ->paginate(25)
                ->withQueryString();

            $hasFilter = true;


            return view('payroll.history', [
                'payroll'          => $payroll,
                'payrolls'         => $payrolls,
                'company'          => $company,
                'deptId'           => $deptId,
                'employee'         => $employee,
                'recordType'       => $recordType,
                'users'            => $users,
                'filters'          => array_filter(compact('employee', 'recordType', 'user', 'from', 'to')),
                'hasFilter'        => $hasFilter,
                'searchAllPeriods' => $searchAllPeriods,
                'entries'          => $entries,
            ]);
        }



    public function save(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        $validated = $request->validate([
            'salary_type' => ['required', 'in:Fixed,Rated,Daily'],
        ]);

        [$company] = $this->context($request);
        $conn = DB::connection(self::HRIS_CONNECTION);
        $now  = now();
        $addedby = $request->user()?->name ?? auth()->user()?->name;

        if ($this->isPosted($conn, $payroll, $idno)) {
            return $this->postedResponse($request, $payroll, $idno);
        }

        // Totals are always recalculated here, with the salary type being saved —
        // never taken from the browser, which may hold stale or edited values.
        $calculated = $this->calculateFor($conn, $payroll, $idno, $company, $validated['salary_type'])['totals'];

        $totals = collect($this->calculator->emptyTotals())->keys()
            ->mapWithKeys(fn ($field) => [$field => $calculated[$field] ?? 0])
            ->all();

        $salaryRate = $conn->table('employee_payroll')->where('idno', $idno)->value('salary');

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
            'salary_rate'              => $salaryRate,
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

        $conn->transaction(function () use ($conn, $payroll, $idno, $columns, $addedby, $now, $validated) {
            // Re-checked inside the transaction (with a row lock) so a post that lands mid-save can't be overwritten.
            $existing = $conn->table('payroll_details')
                ->where('payrollperiod', $payroll->id)
                ->where('idno', $idno)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'posted') {
                return;
            }

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
        });

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

    /**
     * Run the payroll engine for one employee and period, with the same
     * inputs the Edit Payroll screen uses. $salaryType overrides the stored
     * type (used when saving with a newly picked type).
     */
    private function calculateFor($conn, Payroll $payroll, string $idno, string $company, ?string $salaryType = null): array
    {
        $employeeDetails = $conn->table('employee_details')->where('idno', $idno)->first();

        $designation   = $employeeDetails->designation ?? 0;
        $department    = $employeeDetails->department ?? 0;
        $blockOtBefore = ($designation == 71) || ($designation == 46 && $department == 1);
        $blEligible    = $this->isBereavementLeaveEligible($employeeDetails->dateofhired ?? null, $payroll->periodto);

        $salaryType ??= $conn->table('employee_payroll')->where('idno', $idno)->value('salary_type') ?? 'Rated';

        return $this->calculator->calculate(
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
    }

    /**
     * Posted payroll is final: it must be unposted from Manage Payroll
     * before anything that feeds it can change.
     */
    private function isPosted($conn, Payroll $payroll, string $idno): bool
    {
        return $conn->table('payroll_details')
            ->where('payrollperiod', $payroll->id)
            ->where('idno', $idno)
            ->where('status', 'posted')
            ->exists();
    }

    private function postedResponse(Request $request, Payroll $payroll, string $idno): RedirectResponse
    {
        return $this->backToEdit($request, $payroll, $idno)
            ->with('error', 'This payroll is already posted. Unpost it from Manage Payroll before making changes.');
    }

    /**
     * The attendance row, but only if it belongs to this employee and is one
     * of the days the engine counts in this payroll period.
     */
    private function attendanceInPeriod($conn, Payroll $payroll, string $idno, int $attendance, string $company): ?object
    {
        $record = $conn->table('attendance')
            ->where('id', $attendance)
            ->where('idno', $idno)
            ->first();

        if (! $record) {
            return null;
        }

        $inPeriod = collect($this->calculateFor($conn, $payroll, $idno, $company)['rows'])
            ->contains('attendance_id', $attendance);

        return $inPeriod ? $record : null;
    }

    /**
     * Compare two stored/submitted values so "60" and "60.00" count as equal.
     */
    private function sameValue($old, $new): bool
    {
        if ($old === null || $old === '' || $new === null || $new === '') {
            return ($old === null || $old === '') && ($new === null || $new === '');
        }

        return abs((float) $old - (float) $new) < 0.005;
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

    private function logAdjustment( $conn,string $idno,int $periodId,string $type,string $category,string $action,string $description,$oldValue,$newValue,Request $request): void
    {
        $changedBy = session('portal_idno')
            ?? session('accounting_idno')
            ?? $request->user()?->idno
            ?? $request->user()?->id;

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