{{-- Month payslip PDF (mid + end side by side) for all salary types; $type picks the breakdown partial --}}
@php
    $f  = fn ($v) => number_format((float) $v, 2);
    $f1 = fn ($v) => number_format((float) $v, 1);
    $range = fn ($from, $to) => $from && $to
        ? date('M j, Y', strtotime($from)) . ' to ' . date('M j, Y', strtotime($to))
        : 'Period Not Found';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip</title>
    <style>
        @page { margin: 5mm; }
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; }
        .watermark {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-image: url('{{ $watermark }}');
            background-repeat: repeat;
            background-size: 100px auto;
            opacity: 0.1;
        }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px; vertical-align: top; text-align: left; }
        .page > tbody > tr > td { padding: 0; }
        .slip { border: 1px solid #000; }
        .cols > tbody > tr > td { width: 50%; padding: 0 4px; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .indent { padding-left: 14px; }
        .bar { text-align: center; background-color: #1d2437; color: #fff; padding: 4px; margin: 4px 0; font-weight: bold; font-size: 9px; }
        .highlight td { background-color: #b0bfc5; font-weight: bold; }
        .big { font-size: 12px; }
        .muted { font-style: italic; color: #555; }
    </style>
</head>
<body>
@if ($watermark)
    <div class="watermark"></div>
@endif

<table class="page">
    <tr>
    @foreach (['mid' => $mid, 'end' => $end] as $side => $p)
        <td style="width: 49%;">
        <div class="slip">
            {{-- Header --}}
            <table class="cols">
                <tr>
                    <td>
                        <table>
                            <tr><td><strong>EMPLOYEE NAME:</strong></td><td><strong>{{ $p['fullname'] }}</strong></td></tr>
                            <tr><td><strong>TEAM:</strong></td><td><strong>{{ $p['team'] }}</strong></td></tr>
                            @if ($type === 'daily')
                                <tr><td><strong>SHIFT:</strong></td><td><strong>{{ $p['shift'] }}</strong></td></tr>
                            @endif
                        </table>
                    </td>
                    <td>
                        <table>
                            @if ($type === 'fixed')
                                <tr><td><strong>FIXED SEMI-MONTHLY SALARY:</strong></td><td class="num"><strong>{{ $f($p['semiMonthly']) }}</strong></td></tr>
                            @else
                                <tr><td><strong>BASIC MONTHLY SALARY:</strong></td><td class="num"><strong>{{ $f($p['monthly']) }}</strong></td></tr>
                                <tr><td><strong>{{ $type === 'daily' ? 'FIXED DAILY RATE:' : 'PAY PER DAY:' }}</strong></td><td class="num"><strong>{{ $f($p['baseRate']) }}</strong></td></tr>
                            @endif
                            <tr><td><strong>PAY PERIOD:</strong></td><td class="num"><strong>{{ $range($p['periodFrom'], $p['periodTo']) }}</strong></td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            @if (! empty($p['missing']))
                <div class="bar">GROSS EARNINGS AND BENEFITS FOR THIS PAY PERIOD</div>
                <p class="muted" style="text-align: center; padding: 20px 0;">NO PAYROLL RECORD FOR THIS PERIOD</p>
            @else
                @php
                    [$payLabel, $pay] = $type === 'fixed'
                        ? ['Fixed Rate Salary', $p['fixedPay']]
                        : ['Basic Salary', $p['basic']];
                    $grossRows = [
                        [$payLabel, $pay],
                        ['Allowances', $p['allowance']],
                        ['Incentives', $p['incentive']],
                        ['Reimbursements', $p['reimbursement']],
                        ['Company Paid Government Benefits', $p['govTotal']],
                        ['Other Company Paid Benefits', $p['otherTotal']],
                    ];
                    $takeRows = [
                        [$payLabel, $f($pay)],
                        ['Add On Pay', $f($p['totalAddons'])],
                        ['Deductions', '- ' . $f($p['totalDeductions'])],
                    ];
                @endphp

                {{-- Gross / Take home --}}
                <table class="cols">
                    <tr>
                        <td>
                            <div class="bar">GROSS EARNINGS AND BENEFITS FOR THIS PAY PERIOD</div>
                            <table>
                                @foreach ($grossRows as [$label, $amt])
                                    <tr><td class="indent">{{ $label }}</td><td class="num">{{ $f($amt) }}</td></tr>
                                @endforeach
                                <tr class="highlight"><td>TOTAL GROSS EARNINGS &amp; BENEFITS</td><td class="num big">{{ $f($p['gross']) }}</td></tr>
                            </table>
                        </td>
                        <td>
                            <div class="bar">TAKE HOME PAY COMPUTATION</div>
                            <table>
                                @for ($i = 0; $i < count($grossRows); $i++)
                                    <tr><td class="indent">{{ $takeRows[$i][0] ?? '' }}&nbsp;</td><td class="num">{{ $takeRows[$i][1] ?? '' }}</td></tr>
                                @endfor
                                <tr class="highlight"><td>TOTAL TAKE HOME PAY</td><td class="num big">{{ $f($p['takeHome']) }}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>

                {{-- Breakdown (salary-type specific) --}}
                <div class="bar" style="margin: 4px;">BREAKDOWN OF SALARY AND BENEFITS</div>
                @include('payroll.payslip.pdf.' . $type)

                {{-- Benefits --}}
                <table class="cols">
                    <tr>
                        <td>
                            <table>
                                <tr><td colspan="2"><strong>Government Benefits Breakdown</strong></td></tr>
                                @foreach ($p['govBen'] as $label => $amt)
                                    <tr><td class="indent">{{ $label }}</td><td class="num">{{ $f($amt) }}</td></tr>
                                @endforeach
                                <tr class="highlight"><td>TOTAL COMPANY PAID GOVERNMENT BENEFITS</td><td class="num big">{{ $f($p['govTotal']) }}</td></tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr><td colspan="2"><strong>Company Paid Benefits</strong></td></tr>
                                @foreach ($p['otherBen'] as $label => $amt)
                                    <tr><td class="indent">{{ $label }}</td><td class="num">{{ $f($amt) }}</td></tr>
                                @endforeach
                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                                <tr class="highlight"><td>TOTAL OTHER COMPANY PAID BENEFITS</td><td class="num big">{{ $f($p['otherTotal']) }}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endif

            {{-- Signatory --}}
            <div style="margin: 16px 6px 8px;">
                <p>Attested correct and true by:</p>
                <br><br>
                <p><strong>{{ $signatory['name'] }}</strong><br>{{ $signatory['title'] }}</p>
            </div>
        </div>
        </td>
        @if ($side === 'mid')
            <td style="width: 2%;">&nbsp;</td>
        @endif
    @endforeach
    </tr>
</table>
</body>
</html>
