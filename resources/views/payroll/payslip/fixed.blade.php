@php $f = fn ($v) => number_format($v, 2); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/iconhris_2.png') }}">
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        .header-table td { border: none; padding: 8px; vertical-align: middle; }
        .highlight { background-color: #b0bfc5; font-weight: bold; }
        .num { text-align: right; white-space: nowrap; }
        .indent { padding-left: 20px !important; }
        .bar { text-align: center; background-color: #1d2437; color: white; padding: 5px; }
        .row2 { display: flex; justify-content: space-between; }
        .col { width: 49%; }
        .background { position: relative; }
        .background::before {
            content: ""; position: absolute; inset: 0;
            background: url('{{ asset('img/3.png') }}') center/cover no-repeat;
            opacity: 0.1; z-index: -1;
        }
    </style>
</head>
<body>
<div class="background" style="border:2px solid black; padding:10px; width:47%; font-size:70%; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">

    {{-- Header --}}
    <div class="row2">
        <div class="col">
            <table class="header-table">
                <tr><td><strong>EMPLOYEE NAME:</strong></td><td style="padding-left:70px"><strong>{{ $fullname }}</strong></td></tr>
                <tr><td><strong>TEAM:</strong></td><td style="padding-left:70px"><strong>{{ $team }}</strong></td></tr>
            </table>
        </div>
        <div class="col">
            <table class="header-table">
                <tr><td><strong>FIXED SEMI-MONTHLY SALARY:</strong></td><td class="num"><strong>{{ $f($semiMonthly) }}</strong></td></tr>
                <tr><td><strong>Pay Period:</strong></td>
                    <td class="num"><strong>{{ date('M j, Y', strtotime($periodFrom)) }} to {{ date('M j, Y', strtotime($periodTo)) }}</strong></td></tr>
            </table>
        </div>
    </div>

    {{-- Gross / Take home --}}
    @php
        $grossRows = [
            ['Fixed Rate Salary', $fixedPay],
            ['Allowances', $allowance],
            ['Incentives', $incentive],
            ['Reimbursements', $reimbursement],
            ['Company Paid Government Benefits', $govTotal],
            ['Other Company Paid Benefits', $otherTotal],
        ];
        $takeRows = [
            ['Fixed Rate Salary', $f($fixedPay)],
            ['Add On Pay', $f($totalAddons)],
            ['Deductions', '- ' . $f($totalDeductions)],
        ];
    @endphp
    <div class="row2">
        <div class="col">
            <h4 class="bar">GROSS EARNINGS AND BENEFITS FOR THIS PAY PERIOD</h4>
            <table class="header-table">
                @foreach ($grossRows as [$label, $amt])
                    <tr><td class="indent">{{ $label }}</td><td class="num">{{ $f($amt) }}</td></tr>
                @endforeach
                <tr class="highlight">
                    <td><strong>TOTAL GROSS EARNINGS & BENEFITS FOR THIS PAY PERIOD</strong></td>
                    <td class="num" style="font-size:15px"><strong>{{ $f($gross) }}</strong></td>
                </tr>
            </table>
        </div>
        <div class="col">
            <h4 class="bar">TAKE HOME PAY COMPUTATION</h4>
            <table class="header-table">
                @for ($i = 0; $i < count($grossRows); $i++)
                    <tr>
                        <td class="indent">{!! $takeRows[$i][0] ?? '&nbsp;' !!}</td>
                        <td class="num">{!! $takeRows[$i][1] ?? '&nbsp;' !!}</td>
                    </tr>
                @endfor
                <tr class="highlight">
                    <td><strong>TOTAL TAKE HOME PAY FOR THIS PAY PERIOD</strong></td>
                    <td class="num" style="font-size:15px"><strong>{{ $f($takeHome) }}</strong></td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Breakdown: deductions (left) and add-ons (right), rows aligned --}}
    @php $maxRows = max($deductions->count(), $addons->count(), 1); @endphp
    <h4 class="bar">BREAKDOWN OF SALARY AND BENEFITS</h4>
    <div class="row2">
        <div class="col">
            <table class="header-table">
                <tr><td><strong>Deductions Breakdown</strong></td><td class="num"><strong>Amount</strong></td></tr>
                @for ($i = 0; $i < $maxRows; $i++)
                    @php $d = $deductions[$i] ?? null; @endphp
                    <tr>
                        @if ($d)
                            <td class="indent">{{ $d->description }}</td><td class="num">{{ $f($d->amount) }}</td>
                        @elseif ($i === 0 && $deductions->isEmpty())
                            <td class="indent" colspan="2"><i style="color:#555">NO DEDUCTIONS FOR THIS PAYROLL</i></td>
                        @else
                            <td class="indent">&nbsp;</td><td>&nbsp;</td>
                        @endif
                    </tr>
                @endfor
                <tr class="highlight">
                    <td class="indent"><strong>TOTAL DEDUCTIONS</strong></td>
                    <td class="num" style="font-size:15px"><strong>{{ $f($totalDeductions) }}</strong></td>
                </tr>
            </table>
        </div>
        <div class="col">
            <table class="header-table">
                <tr><td><strong>Add On Pay Breakdown</strong></td><td class="num"><strong>Amount</strong></td></tr>
                @for ($i = 0; $i < $maxRows; $i++)
                    @php $a = $addons[$i] ?? null; @endphp
                    <tr>
                        @if ($a)
                            <td class="indent">{{ $a->description }}</td><td class="num">{{ $f($a->amount) }}</td>
                        @elseif ($i === 0 && $addons->isEmpty())
                            <td class="indent" colspan="2"><i style="color:#555">NO ADD-ONS FOR THIS PAYROLL</i></td>
                        @else
                            <td class="indent">&nbsp;</td><td>&nbsp;</td>
                        @endif
                    </tr>
                @endfor
                <tr class="highlight">
                    <td class="indent"><strong>TOTAL ADD ON PAY</strong></td>
                    <td class="num" style="font-size:15px"><strong>{{ $f($totalAddons) }}</strong></td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Benefits --}}
    <div class="row2" style="margin-top:20px">
        <div class="col">
            <table class="header-table">
                <tr><td colspan="2"><strong>Government Benefits Breakdown</strong></td></tr>
                @foreach ($govBen as $label => $amt)
                    <tr><td class="indent">{{ $label }}</td><td class="num">{{ $f($amt) }}</td></tr>
                @endforeach
                <tr class="highlight">
                    <td><strong>TOTAL COMPANY PAID GOVERNMENT BENEFITS</strong></td>
                    <td class="num" style="font-size:15px"><strong>{{ $f($govTotal) }}</strong></td>
                </tr>
            </table>
        </div>
        <div class="col">
            <table class="header-table">
                <tr><td colspan="2"><strong>Company Paid Benefits</strong></td></tr>
                @foreach ($otherBen as $label => $amt)
                    <tr><td class="indent">{{ $label }}</td><td class="num">{{ $f($amt) }}</td></tr>
                @endforeach
                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr class="highlight">
                    <td><strong>TOTAL OTHER COMPANY PAID BENEFITS</strong></td>
                    <td class="num" style="font-size:15px"><strong>{{ $f($otherTotal) }}</strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>