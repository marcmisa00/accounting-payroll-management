<?php
// app/Services/PayslipService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class PayslipService
{
    private const DAYS_PER_MONTH = 20; // original: salary * 20

    public function rated(int $detailId): array
    {   
        $hris = DB::connection('hris');
        $detail = $hris->table('payroll_details')->where('id', $detailId)->first();
        abort_if(! $detail, 404);

        $employee = $hris->table('employee_profile as ep')
            ->join('employee_details as ed', 'ep.idno', '=', 'ed.idno')
            ->join('department as dp', 'dp.id', '=', 'ed.department')
            ->where('ep.idno', $detail->idno)
            ->select('ep.firstname', 'ep.lastname', 'ep.suffix', 'dp.department as team')
            ->first();
        abort_if(! $employee, 404);

        $ep     = $hris->table('employee_payroll')->where('idno', $detail->idno)->first();
        $period = $hris->table('payroll')->where('id', $detail->payrollperiod)->first();
        abort_if(! $ep || ! $period, 404);

        $n = fn (string $col): float => (float) ($detail->$col ?? 0);

        // ---------- Government / company benefits ----------
        $govBen = [
            'SSS Contribution'        => (float) $ep->sss,
            'Philhealth Contribution' => (float) $ep->phic,
            'Pag-ibig Contribution'   => (float) $ep->hdmf,
            'Withholding Tax'         => (float) $ep->tax,
        ];
        $otherBen = [
            'Life Insurance'         => (float) $ep->generali,
            'Philcare (HMO)'         => (float) $ep->philcare,
            'FWD Retirement Benefit' => (float) $ep->fwd,
        ];
        $govTotal   = array_sum($govBen);
        $otherTotal = array_sum($otherBen);

        // ---------- Add-ons / deductions ----------
        $addons = $hris->table('payroll_addons')
            ->where('idno', $detail->idno)->where('payrollperiod', $detail->payrollperiod)->get();
        $deductions = $hris->table('payroll_deductions')
            ->where('idno', $detail->idno)->where('payrollperiod', $detail->payrollperiod)->get();

        $sumMatching = fn (string $word) => (float) $addons
            ->filter(fn ($a) => stripos($a->description, $word) !== false)
            ->sum('amount');

        $allowance     = $sumMatching('allowance');
        $incentive     = $sumMatching('incentive');
        $reimbursement = $sumMatching('reimbursement');
        $totalAddons     = (float) $addons->sum('amount');
        $totalDeductions = (float) $deductions->sum('amount');

        // ---------- Basic salary breakdown ----------
        $hasPrev = $n('reghours_prev') > 0;               // original condition for subtracting prev rate
        $cur = fn (string $col, ?string $prev = null) =>
            $n($col) - ($hasPrev && $prev ? $n($prev) : 0);

        $leavePay = $n('paidvlamount') + $n('paidslamount') + $n('paidblamount')
                  + $n('bdayleaveamount') + $n('paidptlamount') + $n('paidsplamount');

        $regAmount = $hasPrev
            ? $n('totalbasesalary') - $n('totalbasesalary_prev') - $leavePay
            : $n('totalbasesalary') - $leavePay + $n('doubleholidaypay');

        $notWorkHrs = $n('regholidayhrsnotwork');
        $notWorkAmt = $n('regholidayamountnotwork');
        if ($notWorkHrs < 0) { $notWorkHrs = $notWorkAmt = 0; }

        $rows = [
            ['Regular Hours',                    $cur('reghours', 'reghours_prev'),       $regAmount],
            ['RegHours / Overtime',              $cur('reghoursot', 'reghoursot_prev'),   $cur('reghoursotamount', 'reghoursotamount_prev')],
            ['Regular Holiday (Not Worked)',     $notWorkHrs,                             $notWorkAmt],
            ['Regular Holiday (Worked)',         $cur('regholidayhrs', 'regholidayhrs_prev'), $cur('totalregholiday', 'regholidayamount_prev')],
            ['OT for Regular Holiday',           $cur('regholidayothrs', 'regholidayothrs_prev'), $cur('regholidayotamount', 'regholidayotamount_prev')],
            ['Special Non Working Holiday',      $cur('spholidayhrs', 'spholidayhrs_prev'),   $cur('totalspholiday', 'spholidayamount_prev')],
            ['OT for Special Non Working Holiday', $cur('spholidayothrs', 'spholidayothrs_prev'), $cur('spholidayotamount', 'spholidayotamount_prev')],
            ['Paid Vacation',                    $cur('paidvlhrs', 'paidvlhrs_prev'),     $cur('paidvlamount', 'paidvlamount_prev')],
            ['Paid Sick',                        $n('paidslhrs'),                         $n('paidslamount')],
            ['Paid Bereavement Leave',           $n('paidblhrs'),                         $n('paidblamount')],
            ['Paid Birthday Leave',              $cur('bdayleavehrs', 'bdayleavehrs_prev'), $cur('bdayleaveamount', 'bdayleaveamount_prev')],
            ['Paid Paternity Leave',             $n('paidptlhrs'),                        $n('paidptlamount')],
            ['Paid Solo Parent Leave',           $n('paidsplhrs'),                        $n('paidsplamount')],
            ['Night Differential',               $cur('ndhrs', 'ndhrs_prev'),             $cur('ndamount', 'ndamount_prev')],
        ];
        $rows = array_values(array_filter($rows, fn ($r) => round($r[1], 2) != 0));

        $prevRows = [];
        if ($n('totalbasesalary_prev') > 0) {
            $prevRows = array_values(array_filter([
                ['Regular Hours',                        $n('reghours_prev'),          $n('totalbasesalary_prev')],
                ['RegHours / Overtime',                  $n('reghoursot_prev'),        $n('reghoursotamount_prev')],
                ['Regular Holiday (Worked)',             $n('regholidayhrs_prev'),     $n('regholidayamount_prev')],
                ['Regular Holiday (Not Worked)',         $n('regholidayhrsnotworked_prev'), $n('regholidayamountnotworked_prev')],
                ['Special Non-Working Holiday (Worked)', $n('spholidayhrs_prev'),      $n('spholidayamount_prev')],
                ['OT for Regular Holiday',               $n('regholidayothrs_prev'),   $n('regholidayotamount_prev')],
                ['OT for Special Non-Working Holiday',   $n('spholidayothrs_prev'),    $n('spholidayotamount_prev')],
                ['Night Differential',                   $n('ndhrs_prev'),             $n('ndamount_prev')],
                ['Paid Vacation',                        $n('paidvlhrs_prev'),         $n('paidvlamount_prev')],
                ['Paid Birthday Leave',                  $n('bdayleavehrs_prev'),      $n('bdayleaveamount_prev')],
            ], fn ($r) => round($r[1], 2) > 0));
        }

        // ---------- Totals ----------
        $basic = $n('totalpay');

        return [
            'fullname'   => trim("{$employee->firstname} {$employee->lastname} {$employee->suffix}"),
            'team'       => $employee->team,
            'baseRate'   => (float) $ep->salary,
            'basePrev'   => (float) $ep->previous_salary,
            'monthly'    => (float) $ep->salary * self::DAYS_PER_MONTH,
            'periodFrom' => $period->periodfrom,
            'periodTo'   => $period->periodto,

            'rows'     => $rows,
            'prevRows' => $prevRows,
            'basic'    => $basic,

            'addons'          => $addons,
            'totalAddons'     => $totalAddons,
            'deductions'      => $deductions,
            'totalDeductions' => $totalDeductions,
            'allowance'       => $allowance,
            'incentive'       => $incentive,
            'reimbursement'   => $reimbursement,

            'govBen'     => $govBen,
            'govTotal'   => $govTotal,
            'otherBen'   => $otherBen,
            'otherTotal' => $otherTotal,

            'gross'    => $basic + $allowance + $incentive + $reimbursement + $govTotal + $otherTotal,
            'takeHome' => $basic + $totalAddons - $totalDeductions,
        ];
    }
}