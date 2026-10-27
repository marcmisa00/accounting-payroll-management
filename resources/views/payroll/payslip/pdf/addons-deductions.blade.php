{{-- Rated / Daily right column: add-ons then deductions, stacked --}}
<table>
    <tr><td colspan="2"><strong>Add On Pay Breakdown</strong></td></tr>
    @forelse ($p['addons'] as $a)
        <tr><td class="indent">{{ $a->description }}</td><td class="num">{{ $f($a->amount) }}</td></tr>
    @empty
        <tr><td colspan="2" class="indent muted">NO ADD-ONS FOR THIS PAYROLL</td></tr>
    @endforelse
    <tr class="highlight"><td>TOTAL ADD ON PAY</td><td class="num big">{{ $f($p['totalAddons']) }}</td></tr>
    <tr><td colspan="2"><strong>Deductions Breakdown</strong></td></tr>
    @forelse ($p['deductions'] as $d)
        <tr><td class="indent">{{ $d->description }}</td><td class="num">{{ $f($d->amount) }}</td></tr>
    @empty
        <tr><td colspan="2" class="indent muted">NO DEDUCTIONS FOR THIS PAYROLL</td></tr>
    @endforelse
    <tr class="highlight"><td>TOTAL DEDUCTIONS</td><td class="num big">{{ $f($p['totalDeductions']) }}</td></tr>
</table>
