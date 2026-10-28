{{-- Fixed breakdown: deductions (left) and add-ons (right), rows aligned --}}
@php $maxRows = max($p['deductions']->count(), $p['addons']->count(), 1); @endphp
@if (! empty($p['rateSplit']))
    {{-- Salary increase in this period: Fixed Rate Salary split by rate --}}
    <table class="cols">
        <tr>
            <td>
                <table>
                    <tr>
                        <td><strong>Basic Salary Breakdown</strong></td>
                        <td class="center"><strong>Total No. of Days</strong></td>
                        <td class="num"><strong>Amount</strong></td>
                    </tr>
                    <tr><td class="indent">Current Rate {{ $f($p['rateSplit']['new_rate']) }}/day</td><td class="center">{{ $f1($p['rateSplit']['new_days']) }}</td><td class="num">{{ $f($p['rateSplit']['new_amount']) }}</td></tr>
                    <tr><td class="indent">Prev Rate {{ $f($p['rateSplit']['old_rate']) }}/day</td><td class="center">{{ $f1($p['rateSplit']['old_days']) }}</td><td class="num">{{ $f($p['rateSplit']['old_amount']) }}</td></tr>
                    <tr class="highlight"><td>TOTAL FIXED RATE SALARY</td><td></td><td class="num big">{{ $f($p['fixedPay']) }}</td></tr>
                </table>
            </td>
            <td></td>
        </tr>
    </table>
@endif
<table class="cols">
    <tr>
        <td>
            <table>
                <tr><td><strong>Deductions Breakdown</strong></td><td class="num"><strong>Amount</strong></td></tr>
                @for ($i = 0; $i < $maxRows; $i++)
                    @php $d = $p['deductions'][$i] ?? null; @endphp
                    <tr>
                        @if ($d)
                            <td class="indent">{{ $d->description }}</td><td class="num">{{ $f($d->amount) }}</td>
                        @elseif ($i === 0)
                            <td class="indent muted" colspan="2">NO DEDUCTIONS FOR THIS PAYROLL</td>
                        @else
                            <td>&nbsp;</td><td>&nbsp;</td>
                        @endif
                    </tr>
                @endfor
                <tr class="highlight"><td class="indent">TOTAL DEDUCTIONS</td><td class="num big">{{ $f($p['totalDeductions']) }}</td></tr>
            </table>
        </td>
        <td>
            <table>
                <tr><td><strong>Add On Pay Breakdown</strong></td><td class="num"><strong>Amount</strong></td></tr>
                @for ($i = 0; $i < $maxRows; $i++)
                    @php $a = $p['addons'][$i] ?? null; @endphp
                    <tr>
                        @if ($a)
                            <td class="indent">{{ $a->description }}</td><td class="num">{{ $f($a->amount) }}</td>
                        @elseif ($i === 0)
                            <td class="indent muted" colspan="2">NO ADD-ONS FOR THIS PAYROLL</td>
                        @else
                            <td>&nbsp;</td><td>&nbsp;</td>
                        @endif
                    </tr>
                @endfor
                <tr class="highlight"><td class="indent">TOTAL ADD ON PAY</td><td class="num big">{{ $f($p['totalAddons']) }}</td></tr>
            </table>
        </td>
    </tr>
</table>
