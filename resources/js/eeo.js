// EEO Application page: tabs and search come from lv-tabs.js; this file
// only draws one EEO row.

import { initLvPage, el, dash, clipped, cell } from './lv-tabs.js';

const STATUS_LABELS = {
    approved: 'Approved',
    disapproved: 'Disapproved',
    cancelled: 'Cancelled',
    pending: 'Pending',
    other: 'Other',
};

function statusCell(row) {
    const td = el('td', 'lv-status');
    const chip = el('span', 'lv-chip chip-' + row.status, row.statusLabel || STATUS_LABELS[row.status] || 'Other');

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

// Reason, with a pin that opens the proof file (hosted on the HRIS portal) in a new tab
function reasonCell(row) {
    const td = el('td', 'lv-long');

    if (row.proof) {
        const pin = el('a', 'eeo-proof');
        pin.href = row.proof.url;
        pin.target = '_blank';
        pin.rel = 'noopener noreferrer';
        pin.title = 'View attached proof' + (row.proof.uploaded ? '\nUploaded: ' + row.proof.uploaded : '');
        pin.setAttribute('aria-label', 'View attached proof');
        pin.append(el('i', 'fa-solid fa-paperclip'));
        td.append(pin);
    }

    td.append(clipped(row.reason));
    return td;
}

function acknowledgedCell(list) {
    const td = el('td', 'lv-long');
    if (!list.length) {
        td.append(dash());
        return td;
    }

    const ul = el('ul', 'eeo-ack');
    list.forEach(function (entry) { ul.append(el('li', null, entry)); });
    td.append(ul);
    return td;
}

document.addEventListener('DOMContentLoaded', function () {
    initLvPage({
        dataId: 'eeoData',
        haystack: function (row) { return row.name + ' ' + row.id + ' ' + row.type; },
        cells: function (row) {
            return [
                el('td', 'lv-id', row.id),
                el('td', 'lv-name', row.name),
                cell(null, el('span', 'lv-type', row.type)),
                el('td', 'lv-date', row.date || '—'),
                el('td', 'lv-date', row.time || '—'),
                reasonCell(row),
                statusCell(row),
                cell('lv-long', clipped(row.apNotes)),
                acknowledgedCell(row.acknowledged),
            ];
        },
    });
});
