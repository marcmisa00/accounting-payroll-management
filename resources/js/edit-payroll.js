const prevId = window.payrollData?.prevId;
const nextId = window.payrollData?.nextId;

const prevUrl = window.payrollData?.prevUrl;
const nextUrl = window.payrollData?.nextUrl;


// ==========================================
// PREVIOUS / NEXT EMPLOYEE
// ==========================================

document.addEventListener('keydown', function (e) {

    // Don't trigger while typing in a form field
    if (
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) ||
        document.activeElement.isContentEditable
    ) {
        return;
    }

    // Previous employee
    if (e.key === '[' && prevUrl) {
        window.location.href = prevUrl;
    }

    // Next employee
    else if (e.key === ']' && nextUrl) {
        window.location.href = nextUrl;
    }
});