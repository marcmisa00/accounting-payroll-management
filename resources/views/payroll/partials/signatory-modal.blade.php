<!-- Payslip Signatory Selection (behaviour: resources/js/payslip-export.js) -->
<div class="signatory-overlay" id="signatoryModal">
    <div class="signatory-box">
        <p>Select Signatory</p>
        <select id="signatorySelect">
            @foreach (config('payroll.payslip_signatories') as $key => $signatory)
                <option value="{{ $key }}">{{ $signatory['name'] }} - {{ $signatory['title'] }}</option>
            @endforeach
        </select>
        <div class="signatory-actions">
            <button type="button" class="btn btn-primary" id="signatoryCancel">Cancel</button>
            <button type="button" class="btn btn-success" id="signatoryExport"><i class="fa fa-download"></i> Export</button>
        </div>
    </div>
</div>
