<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PayrollController extends Controller
{
    /**
     * The connection all the surrounding HRIS tables (employee_details,
     * department, payroll_details, etc.) live on, same as the Payroll model.
     */
    private const HRIS_CONNECTION = 'hris';

    /**
     * Show the "Create Payroll" form.
     * (was: createpayroll.php)
     */
    public function create(): View
    {
        return view('payroll.create');
    }

    /**
     * Handle submission of the "Create Payroll" form.
     * Only Period From / Period To are collected now — the old
     * "Period" (mid/end) and "Company" selects have been removed.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'startdate' => ['required', 'date'],
            'enddate'   => ['required', 'date', 'after_or_equal:startdate'],
        ]);

        // Reuse an existing payroll period if one already covers this exact range.
        $payroll = Payroll::where('periodfrom', $validated['startdate'])
            ->where('periodto', $validated['enddate'])
            ->first();

        if (! $payroll) {
            $payroll = Payroll::create([
                'periodfrom'    => $validated['startdate'],
                'periodto'      => $validated['enddate'],
                'days'          => $this->countWorkingDays($validated['startdate'], $validated['enddate']),
                'addedby'       => $request->user()?->name ?? auth()->user()?->name,
                'addeddatetime' => now(),
            ]);
        }

        return redirect()->route('payroll.show', $payroll->id);
    }

    /**
     * Show the period-selection form for managing an existing payroll.
     * (was: viewpayroll.php) — Company select has been removed, period only.
     */
    public function manageSelect(): View
    {
        $periods = Payroll::orderByDesc('id')->get();

        return view('payroll.manage', compact('periods'));
    }

    /**
     * Handle submission of the period-selection form.
     */
    public function manageSelectSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'exists:hris.payroll,id'],
        ]);

        return redirect()->route('payroll.show', $validated['period']);
    }

    /**
     * The actual "Manage Payroll" screen for a chosen period.
     * (was: managepayroll.php)
     *
     * The old page took period + company and showed one company's
     * departments as tabs. Company selection is gone now, so this shows
     * every company as its own top-level tab, each with its departments
     * nested inside as before.
     */
    public function show(Request $request, Payroll $payroll): View
    {
        $searchTerm   = trim((string) $request->query('search', ''));
        $searchColumn = (string) $request->query('searchColumn', 'all');

        $companies = DB::connection(self::HRIS_CONNECTION)
            ->table('settings')
            ->select('companycode', 'companyname')
            ->get();

        $companiesData = $companies->map(function ($company) use ($payroll, $searchTerm, $searchColumn) {
            return $this->buildCompanyPayrollData(
                $payroll->id,
                $company->companycode,
                $company->companyname,
                $searchTerm,
                $searchColumn
            );
        });

        return view('payroll.show', [
            'payroll'      => $payroll,
            'companies'    => $companiesData,
            'searchTerm'   => $searchTerm,
            'searchColumn' => $searchColumn,
        ]);
    }

    /**
     * Post all pending payslips for one company in this payroll period.
     * (was: the "postpayslip" branch in managepayroll.php)
     */
    public function postPayslip(Request $request, Payroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'company' => ['required', 'string'],
        ]);

        DB::connection(self::HRIS_CONNECTION)
            ->table('payroll_details as pd')
            ->join('employee_details as ed', 'ed.idno', '=', 'pd.idno')
            ->where('pd.payrollperiod', $payroll->id)
            ->where('ed.company', $validated['company'])
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->where('pd.status', 'pending')
            ->update([
                'pd.status'     => 'posted',
                'pd.dateposted' => now(),
            ]);

        return redirect()
            ->route('payroll.show', $payroll)
            ->with('success', 'Payslip successfully posted!');
    }

    /**
     * Undo posting for one company in this payroll period.
     * (was: the "undopostpayslip" branch in managepayroll.php)
     */
    public function undoPostPayslip(Request $request, Payroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'company' => ['required', 'string'],
        ]);

        DB::connection(self::HRIS_CONNECTION)
            ->table('payroll_details as pd')
            ->join('employee_details as ed', 'ed.idno', '=', 'pd.idno')
            ->where('pd.payrollperiod', $payroll->id)
            ->where('ed.company', $validated['company'])
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->where('pd.status', 'posted')
            ->update([
                'pd.status'     => 'pending',
                'pd.dateposted' => null,
            ]);

        return redirect()
            ->route('payroll.show', $payroll)
            ->with('success', 'Payslip successfully unposted!');
    }

    /**
     * Build everything the view needs for one company's tab: posted/not-posted
     * counts, its departments, and each department's employee rows with pay
     * figures — mirroring the original per-company queries in managepayroll.php.
     */
    private function buildCompanyPayrollData(
        int $periodId,
        string $comp,
        string $companyName,
        string $searchTerm,
        string $searchColumn
    ): array {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $statusRows = $conn->table('payroll_details as pd')
            ->join('employee_details as ed', 'ed.idno', '=', 'pd.idno')
            ->join('employee_payroll as ep', 'ep.idno', '=', 'ed.idno')
            ->where('pd.payrollperiod', $periodId)
            ->where('ed.company', $comp)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->select('pd.status')
            ->get();

        $posted    = $statusRows->where('status', 'posted')->count();
        $notposted = $statusRows->count() - $posted;

        $departments = $conn->table('employee_details as ed')
            ->join('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.company', $comp)
            ->where('ed.status', '!=', 'RESIGNED')
            ->select('d.id', 'd.department')
            ->distinct()
            ->orderBy('d.department')
            ->get();

        $idnos = $conn->table('employee_details')
            ->where('company', $comp)
            ->where('status', 'not like', '%RESIGNED%')
            ->pluck('idno');

        $payrollDetails  = collect();
        $deductions      = collect();
        $addons          = collect();
        $employeePayroll = collect();

        if ($idnos->isNotEmpty()) {
            $payrollDetails = $conn->table('payroll_details')
                ->whereIn('idno', $idnos)
                ->where('payrollperiod', $periodId)
                ->get()
                ->keyBy('idno');

            $deductions = $conn->table('payroll_deductions')
                ->whereIn('idno', $idnos)
                ->where('payrollperiod', $periodId)
                ->groupBy('idno')
                ->select('idno', DB::raw('SUM(amount) as amount'))
                ->pluck('amount', 'idno');

            $addons = $conn->table('payroll_addons')
                ->whereIn('idno', $idnos)
                ->where('payrollperiod', $periodId)
                ->groupBy('idno')
                ->select('idno', DB::raw('SUM(amount) as amount'))
                ->pluck('amount', 'idno');

            $employeePayroll = $conn->table('employee_payroll')
                ->whereIn('idno', $idnos)
                ->get()
                ->keyBy('idno');
        }

        $departmentsData = $departments->map(function ($dept) use (
            $conn, $comp, $searchTerm, $searchColumn,
            $payrollDetails, $deductions, $addons, $employeePayroll
        ) {
            $employees = $this->fetchDepartmentEmployees($conn, $comp, $dept->id, $searchTerm, $searchColumn);

            $rows = $employees->map(function ($employee) use ($payrollDetails, $deductions, $addons, $employeePayroll) {
                $idno = $employee->idno;

                $payrollRow = $payrollDetails->get($idno);
                $totalpay   = (float) ($payrollRow->totalpay ?? 0);
                $payrollId  = $payrollRow->id ?? null;

                $deductionAmt = (float) ($deductions->get($idno) ?? 0);
                $addonAmt     = (float) ($addons->get($idno) ?? 0);
                $netpay       = ($totalpay + $addonAmt) - $deductionAmt;

                $typeSalary = optional($employeePayroll->get($idno))->salary_type;

                $startTime    = $employee->startshift ? date('h:i A', strtotime($employee->startshift)) : null;
                $isNightShift = in_array($startTime, ['12:00 AM', '01:00 AM', '11:00 PM'], true);

                $bgColor = $isNightShift ? '#cccccc' : '';
                if (in_array($typeSalary, ['Fixed', 'Daily'], true)) {
                    $bgColor = '#d0e1f1';
                }

                return [
                    'idno'        => $idno,
                    'name'        => trim("{$employee->lastname}, {$employee->firstname} {$employee->middlename} {$employee->suffix}"),
                    'location'    => $employee->location,
                    'addons'      => $addonAmt,
                    'totalpay'    => $totalpay,
                    'deductions'  => $deductionAmt,
                    'netpay'      => $netpay,
                    'payroll_id'  => $payrollId,
                    'salary_type' => $typeSalary,
                    'bg_color'    => $bgColor,
                ];
            });

            return [
                'id'               => $dept->id,
                'name'             => $dept->department,
                'employees'        => $rows,
                'has_search_match' => $searchTerm !== '' && $rows->isNotEmpty(),
            ];
        });

        return [
            'code'        => $comp,
            'name'        => $companyName,
            'posted'      => $posted,
            'notposted'   => $notposted,
            'departments' => $departmentsData,
        ];
    }

    /**
     * Fetch employees for one department under one company, applying the
     * same "search all columns / Emp ID / Employee Name / Setup" filter
     * as the original page.
     */
    private function fetchDepartmentEmployees($conn, string $comp, int $deptId, string $searchTerm, string $searchColumn)
    {
        $query = $conn->table('employee_profile as ep')
            ->join('employee_details as ed', 'ed.idno', '=', 'ep.idno')
            ->join('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.company', $comp)
            ->where('ed.department', $deptId)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->select('ep.*', 'ed.*', 'd.department');

        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';

            $query->where(function ($q) use ($searchColumn, $like) {
                match ($searchColumn) {
                    '1'     => $q->where('ep.idno', 'like', $like),
                    '2'     => $q->where(DB::raw("CONCAT(ep.lastname,' ',ep.firstname,' ',ep.middlename)"), 'like', $like),
                    '3'     => $q->where('ed.location', 'like', $like),
                    default => $q->where('ep.idno', 'like', $like)
                        ->orWhere('ep.lastname', 'like', $like)
                        ->orWhere('ep.firstname', 'like', $like)
                        ->orWhere('ed.location', 'like', $like),
                };
            });
        }

        return $query->orderBy('ep.lastname')->get();
    }

    /**
     * Count weekdays in the range, replicating the legacy rule exactly:
     * Monday (ISO 1) and Sunday (ISO 7) are excluded, Tue–Sat are counted.
     * Carried over as-is from the original PHP logic.
     */
    private function countWorkingDays(string $startdate, string $enddate): int
    {
        $start = Carbon::parse($startdate);
        $end   = Carbon::parse($enddate);

        $count = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $isoDay = (int) $date->isoWeekday(); // 1 = Monday ... 7 = Sunday
            if ($isoDay !== 1 && $isoDay !== 7) {
                $count++;
            }
        }

        return $count;
    }
}