import '../css/payslip-export.css';

// Payslip export: pick a signatory first, then open the PDF in a new tab.
// Markup: resources/views/payroll/partials/signatory-modal.blade.php
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('signatoryModal');

    if (!modal) {
        return;
    }

    const select = document.getElementById('signatorySelect');
    let exportUrl = null;

    document.querySelectorAll('.export-payslip-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            exportUrl = btn.dataset.url;
            select.value = btn.dataset.signatory;
            modal.classList.add('open');
        });
    });

    // Close on Cancel or on a click outside the box
    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.id === 'signatoryCancel') {
            modal.classList.remove('open');
        }
    });

    document.getElementById('signatoryExport').addEventListener('click', function () {
        window.open(exportUrl + '?signatory=' + encodeURIComponent(select.value), '_blank');
        modal.classList.remove('open');
    });
});
