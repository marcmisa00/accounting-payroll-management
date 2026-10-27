{{-- Rated breakdown: hours rows (current + previous rate, from PayslipService::rated) | add-ons & deductions --}}
<table class="cols">
    <tr>
        <td>
            <table>
                <tr>
                    <td><strong>Basic Salary Breakdown</strong></td>
                    <td class="center"><strong>Total No. of Hours</strong></td>
                    <td class="num"><strong>Base Rate {{ $f($p['baseRate']) }}/day</strong></td>
                </tr>
                @foreach ($p['rows'] as [$label, $hrs, $amt])
                    <tr><td class="indent">{{ $label }}</td><td class="center">{{ $f($hrs) }}</td><td class="num">{{ $f($amt) }}</td></tr>
                @endforeach
                @if (count($p['prevRows']))
                    <tr>
                        <td><strong>Basic Salary Breakdown</strong></td>
                        <td class="center"><strong>Total No. of Hours</strong></td>
                        <td class="num"><strong>Prev Rate {{ $f($p['basePrev']) }}/day</strong></td>
                    </tr>
                    @foreach ($p['prevRows'] as [$label, $hrs, $amt])
                        <tr><td class="indent">{{ $label }}</td><td class="center">{{ $f($hrs) }}</td><td class="num">{{ $f($amt) }}</td></tr>
                    @endforeach
                @endif
                <tr class="highlight"><td>TOTAL BASIC SALARY</td><td></td><td class="num big">{{ $f($p['basic']) }}</td></tr>
            </table>
        </td>
        <td>
            @include('payroll.payslip.pdf.addons-deductions')
        </td>
    </tr>
</table>
