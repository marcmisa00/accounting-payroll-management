// Overtime page: tabs and search come from lv-tabs.js; this file
// only draws one overtime row.

import { initLvPage, el, clipped, cell } from './lv-tabs.js';

const STATUS_LABELS = {
    approved: 'Approved',
    disapproved: 'Disapproved',
    cancelled: 'Cancelled',
    pending: 'Pending',
    other: 'Other',
};

function statusCell(row) {
    const td = el('td', 'lv-status');
    const chip = el('span', 'lv-chip chip-' + row.status, STATUS_LABELS[row.status] || 'Other');

    if (row.flagged) {
        const star = el('span', 'lv-star', '*');
        star.title = 'This status is marked with * in HRIS';
        chip.append(star);
    }

    td.append(chip);

    if (row.statusBy) {
        td.append(el('small', null, row.statusBy));
    }

    return td;
}

document.addEventListener('DOMContentLoaded', function () {
    initLvPage({
        dataId: 'overtimeData',
        haystack: function (row) { return row.name + ' ' + row.id + ' ' + row.type; },
        cells: function (row) {
            return [
                el('td', 'lv-id', row.id),
                el('td', 'lv-name', row.name),
                el('td', 'lv-date', row.date),
                el('td', 'lv-date', row.time || '—'),
                cell('lv-long', clipped(row.reason)),
                el('td', 'lv-date', row.applied || '—'),
                statusCell(row),
                cell('lv-long', clipped(row.apNotes)),
            ];
        },
    });
});
