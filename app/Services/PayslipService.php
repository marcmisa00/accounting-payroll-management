<?php
// app/Services/PayslipService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class PayslipService
{
    private const DAYS_PER_MONTH = 20; // original: salary * 20

    /* ------------------------------------------------------------------ */
    /*  RATED                                                              */
    /* ------------------------------------------------------------------ */
    public function rated(int $detailId): array
    {
        [$detail, $c] = $this->common($detailId);   // CHANGED: shared lookups
        $n = fn (string $col): float => (float) ($detail->$col ?? 0);

        // ---------- Basic salary breakdown ----------
        $hasPrev = $n('reghours_prev') > 0;
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
            ['Regular Hours',                      $cur('reghours', 'reghours_prev'),               $regAmount],
            ['RegHours / Overtime',                $cur('reghoursot', 'reghoursot_prev'),           $cur('reghoursotamount', 'reghoursotamount_prev')],
            ['Regular Holiday (Not Worked)',       $notWorkHrs,                                     $notWorkAmt],
            ['Regular Holiday (Worked)',           $cur('regholidayhrs', 'regholidayhrs_prev'),     $cur('totalregholiday', 'regholidayamount_prev')],
            ['OT for Regular Holiday',             $cur('regholidayothrs', 'regholidayothrs_prev'), $cur('regholidayotamount', 'regholidayotamount_prev')],
            ['Special Non Working Holiday',        $cur('spholidayhrs', 'spholidayhrs_prev'),       $cur('totalspholiday', 'spholidayamount_prev')],
            ['OT for Special Non Working Holiday', $cur('spholidayothrs', 'spholidayothrs_prev'),   $cur('spholidayotamount', 'spholidayotamount_prev')],
            ['Paid Vacation',                      $cur('paidvlhrs', 'paidvlhrs_prev'),             $cur('paidvlamount', 'paidvlamount_prev')],
            ['Paid Sick',                          $n('paidslhrs'),                                 $n('paidslamount')],
            ['Paid Bereavement Leave',             $n('paidblhrs'),                                 $n('paidblamount')],
            ['Paid Birthday Leave',                $cur('bdayleavehrs', 'bdayleavehrs_prev'),       $cur('bdayleaveamount', 'bdayleaveamount_prev')],
            ['Paid Paternity Leave',               $n('paidptlhrs'),                                $n('paidptlamount')],
            ['Paid Solo Parent Leave',             $n('paidsplhrs'),                                $n('paidsplamount')],
            ['Night Differential',                 $cur('ndhrs', 'ndhrs_prev'),                     $cur('ndamount', 'ndamount_prev')],
        ];
        $rows = array_values(array_filter($rows, fn ($r) => round($r[1], 2) != 0));

        $prevRows = [];
        if ($n('totalbasesalary_prev') > 0) {
            $prevRows = array_values(array_filter([
                ['Regular Hours',                        $n('reghours_prev'),               $n('totalbasesalary_prev')],
                ['RegHours / Overtime',                  $n('reghoursot_prev'),             $n('reghoursotamount_prev')],
                ['Regular Holiday (Worked)',             $n('regholidayhrs_prev'),          $n('regholidayamount_prev')],
                ['Regular Holiday (Not Worked)',         $n('regholidayhrsnotworked_prev'), $n('regholidayamountnotworked_prev')],
                ['Special Non-Working Holiday (Worked)', $n('spholidayhrs_prev'),           $n('spholidayamount_prev')],
                ['OT for Regular Holiday',               $n('regholidayothrs_prev'),        $n('regholidayotamount_prev')],
                ['OT for Special Non-Working Holiday',   $n('spholidayothrs_prev'),         $n('spholidayotamount_prev')],
                ['Night Differential',                   $n('ndhrs_prev'),                  $n('ndamount_prev')],
                ['Paid Vacation',                        $n('paidvlhrs_prev'),              $n('paidvlamount_prev')],
                ['Paid Birthday Leave',                  $n('bdayleavehrs_prev'),           $n('bdayleaveamount_prev')],
            ], fn ($r) => round($r[1], 2) > 0));
        }

        $basic = $n('totalpay');

        // CHANGED: only the Rated-specific keys are returned; the rest come from common()
        return $c + [
            'monthly'  => $c['baseRate'] * self::DAYS_PER_MONTH,
            'rows'     => $rows,
            'prevRows' => $prevRows,
            'basic'    => $basic,
            'gross'    => $basic + $c['allowance'] + $c['incentive'] + $c['reimbursement']
                          + $c['govTotal'] + $c['otherTotal'],
            'takeHome' => $basic + $c['totalAddons'] - $c['totalDeductions'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  FIXED                                                              */
    /* ------------------------------------------------------------------ */
    public function fixed(int $detailId): array
    {
        [$detail, $c] = $this->common($detailId);
        $pay = (float) $detail->totalpay;

        return $c + [
            'semiMonthly' => $c['baseRate'] * 10,   // original: salary * 10
            'fixedPay'    => $pay,
            // original Fixed gross uses ALL add-ons, not just the 3 categories
            'gross'       => $pay + $c['totalAddons'] + $c['govTotal'] + $c['otherTotal'],
            'takeHome'    => $pay + $c['totalAddons'] - $c['totalDeductions'],
        ];
    }


    public function daily(int $detailId): array
        {
            [$detail, $c] = $this->common($detailId);
            $hris = DB::connection('hris');
            $n    = fn (string $col): float => (float) ($detail->$col ?? 0);

            $periodRow = $hris->table('payroll')->where('id', $detail->payrollperiod)->first();
            $from = $periodRow->periodfrom;
            $to   = $periodRow->periodto;

            // Days not counted as regular: leave (except EO / EEO-PO) and absences
            $nonRegDays = $hris->table('attendance')
                ->where('idno', $detail->idno)
                ->whereBetween('logindate', [$from, $to])
                ->where(function ($q) {
                    $q->where(fn ($q) => $q->where('status', 'leave')->whereNotIn('remarks', ['EO', 'EEO-PO']))
                    ->orWhere('status', 'ab');
                })
                ->count();

            // Office-closure holidays in the period
            $closureDays = $hris->table('holidays')
                ->where('type', 'close')
                ->whereBetween('date', [$from, $to])
                ->count();

            $regDays    = ($c['periodDays'] - $closureDays) - $nonRegDays;
            $regDaysPay = $c['baseRate'] * $regDays;

            // Leave rows: hours / 8 = days
            $leave = fn (string $label, string $hrsCol, string $amtCol) =>
                [$label, $n($hrsCol) / 8, $n($amtCol)];

            $leaveRows = array_values(array_filter([
                $leave('Paid Vacation',          'paidvlhrs',   'paidvlamount'),
                $leave('Paid Sick',              'paidslhrs',   'paidslamount'),
                $leave('Paid Bereavement Leave', 'paidblhrs',   'paidblamount'),
                $leave('Paid Birthday Leave',    'bdayleavehrs','bdayleaveamount'),
                $leave('Paid Paternity Leave',   'paidptlhrs',  'paidptlamount'),
                $leave('Paid Solo Parent Leave', 'paidsplhrs',  'paidsplamount'),
            ], fn ($r) => round($r[1], 1) != 0));

            $basic = $n('totalpay');

            return $c + [
                'monthly'    => $c['baseRate'] * self::DAYS_PER_MONTH,
                'regDays'    => $regDays,
                'regDaysPay' => $regDaysPay,
                'leaveRows'  => $leaveRows,
                'ndDays'     => $n('ndhrs') / 8,
                'ndPay'      => $n('ndamount'),
                'basic'      => $basic,
                'gross'      => $basic + $c['allowance'] + $c['incentive'] + $c['reimbursement']
                                + $c['govTotal'] + $c['otherTotal'],
                'takeHome'   => $basic + $c['totalAddons'] - $c['totalDeductions'],
            ];
        }
    /* ------------------------------------------------------------------ */
    /*  SHARED (new)                                                       */
    /* ------------------------------------------------------------------ */
    /** Shared lookups for every payslip type. Returns [rawDetailRow, viewData]. */
    private function common(int $detailId): array
    {
        $hris = DB::connection('hris');

        $detail = $hris->table('payroll_details')->where('id', $detailId)->first();
        abort_if(! $detail, 404);

        $employee = $hris->table('employee_profile as ep')
            ->join('employee_details as ed', 'ep.idno', '=', 'ed.idno')
            ->join('department as dp', 'dp.id', '=', 'ed.department')
            ->where('ep.idno', $detail->idno)
            ->select('ep.firstname', 'ep.lastname', 'ep.suffix', 'dp.department as team', 'ed.startshift', 'ed.endshift')
            ->first();
        $ep     = $hris->table('employee_payroll')->where('idno', $detail->idno)->first();
        $period = $hris->table('payroll')->where('id', $detail->payrollperiod)->first();
        abort_if(! $employee || ! $ep || ! $period, 404);

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

        $where      = ['idno' => $detail->idno, 'payrollperiod' => $detail->payrollperiod];
        $addons     = $hris->table('payroll_addons')->where($where)->get();
        $deductions = $hris->table('payroll_deductions')->where($where)->get();

        $sumMatching = fn (string $word) => (float) $addons
            ->filter(fn ($a) => stripos($a->description, $word) !== false)
            ->sum('amount');

        return [$detail, [
            'fullname'   => trim("{$employee->firstname} {$employee->lastname} {$employee->suffix}"),
            'team'       => $employee->team,
            'baseRate'   => (float) $ep->salary,
            'basePrev'   => (float) $ep->previous_salary,
            'periodFrom' => $period->periodfrom,
            'periodTo'   => $period->periodto,
            'shift'      => $employee->startshift && $employee->endshift ? date('g:i A', strtotime($employee->startshift)) . ' - ' . date('g:i A', strtotime($employee->endshift)): '',
            'periodDays' => (float) ($period->days ?? 0),
            
            'addons'          => $addons,
            'totalAddons'     => (float) $addons->sum('amount'),
            'deductions'      => $deductions,
            'totalDeductions' => (float) $deductions->sum('amount'),
            'allowance'       => $sumMatching('allowance'),
            'incentive'       => $sumMatching('incentive'),
            'reimbursement'   => $sumMatching('reimbursement'),

            'govBen'     => $govBen,
            'govTotal'   => array_sum($govBen),
            'otherBen'   => $otherBen,
            'otherTotal' => array_sum($otherBen),
        ]];
    }
}