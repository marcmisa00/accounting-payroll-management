<?php

namespace App\Http\Controllers;

use App\Services\PayslipService;

class PayslipController extends Controller
{
    public function __construct(
        private PayslipService $payslips
    ) {}

   public function rated(int $detail)
    {
        return view(
            'payroll.payslip.rated',
            $this->payslips->rated($detail)
        );
    }

    public function ratedFromEdit(int $payroll, string $idno, int $detail)
    {
        return view(
            'payroll.payslip.rated',
            $this->payslips->rated($detail)
        );
    }

    public function fixed(int $detail)
    {
        return view(
            'payroll.payslip.fixed',
            $this->payslips->fixed($detail)
        );
    }

    public function fixedFromEdit(int $payroll, string $idno, int $detail)
    {
        return view(
            'payroll.payslip.fixed',
            $this->payslips->fixed($detail)
        );
    }

    public function daily(int $detail)
    {
        return view(
            'payroll.payslip.daily',
            $this->payslips->daily($detail)
        );
    }

    public function dailyFromEdit(int $payroll, string $idno, int $detail)
    {
        return view(
            'payroll.payslip.daily',
            $this->payslips->daily($detail)
        );
    }

}