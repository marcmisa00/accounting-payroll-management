{{-- Daily breakdown: regular days, leave days, night differential (from PayslipService::daily) | add-ons & deductions --}}
<table class="cols">
    <tr>
        <td>
            <table>
                <tr>
                    <td><strong>Basic Salary Breakdown</strong></td>
                    <td class="center"><strong>Total No. of Days</strong></td>
                    <td class="num"><strong>Base Rate {{ $f($p['baseRate']) }}/day</strong></td>
                </tr>
                <tr><td class="indent">Regular Days</td><td class="center">{{ $f1($p['regDays']) }}</td><td class="num">{{ $f($p['regDaysPay']) }}</td></tr>
                @foreach ($p['leaveRows'] as [$label, $days, $amt])
                    <tr><td class="indent">{{ $label }}</td><td class="center">{{ $f1($days) }}</td><td class="num">{{ $f($amt) }}</td></tr>
                @endforeach
                <tr><td class="indent">Night Differential</td><td class="center">{{ $f1($p['ndDays']) }}</td><td class="num">{{ $f($p['ndPay']) }}</td></tr>
                <tr class="highlight"><td>TOTAL BASIC SALARY</td><td></td><td class="num big">{{ $f($p['basic']) }}</td></tr>
            </table>
        </td>
        <td>
            @include('payroll.payslip.pdf.addons-deductions')
        </td>
    </tr>
</table>
