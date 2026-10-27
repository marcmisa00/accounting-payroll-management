<?php

namespace App\Http\Controllers;

use App\Services\PayslipService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function ratedPdf(Request $request, int $detail)
    {
        return $this->pdf($request, 'rated', $detail, 'legal');
    }

    public function fixedPdf(Request $request, int $detail)
    {
        return $this->pdf($request, 'fixed', $detail, 'a4');
    }

    public function dailyPdf(Request $request, int $detail)
    {
        return $this->pdf($request, 'daily', $detail, 'legal');
    }

    /** Streams the month (mid + end) payslip PDF; paper sizes follow the legacy exporttopdf*.php scripts. */
    private function pdf(Request $request, string $type, int $detail, string $paper)
    {
        $signatories = config('payroll.payslip_signatories');
        $key = $request->validate([
            'signatory' => ['required', Rule::in(array_keys($signatories))],
        ])['signatory'];

        $data = $this->payslips->monthly($type, $detail) + ['signatory' => $signatories[$key]];

        return Pdf::loadView('payroll.payslip.pdf.layout', $data)
            ->setPaper($paper, 'landscape')
            ->stream("payslip-{$type}-{$detail}.pdf");
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