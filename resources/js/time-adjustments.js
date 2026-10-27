// Time Adjustment page: tabs and search come from lv-tabs.js; this file
// only draws one time-adjustment row.

import { initLvPage, el, dash, clipped, cell } from './lv-tabs.js';

const FINAL_CLASSES = {
    Approved: 'approved',
    Rejected: 'rejected',
    Archived: 'archived',
};

// Original / Requested: the affected date, then one line per changed field
function dataCell(row, fields, kind) {
    const td = el('td', 'ta-data');
    const card = el('div', 'ta-card ta-' + kind);

    card.append(el('div', 'ta-card-date', row.date || '—'));

    if (fields === null) {
        card.append(el('div', 'ta-card-empty', 'No record found'));
    } else if (!fields.length) {
        card.append(el('div', 'ta-card-empty', 'No changes'));
    } else {
        fields.forEach(function (f) {
            const line = el('div', 'ta-card-row');
            line.append(el('span', 'ta-card-label', f.label), el('span', 'ta-card-value', f.value));
            card.append(line);
        });
    }

    td.append(card);
    return td;
}

function statusCell(row) {
    const td = el('td', 'lv-status');
    td.append(el('span', 'lv-chip chip-' + row.status, row.statusLabel));

    if (row.statusBy) {
        td.append(el('small', null, row.statusBy));
    }

    if (row.final) {
        const final = el('div', 'ta-final');
        final.append('Final: ', el('span', 'lv-chip chip-' + (FINAL_CLASSES[row.final] || 'other'), row.final));
        td.append(final);

        if (row.finalBy) {
            td.append(el('small', null, row.finalBy));
        }
    }

    return td;
}

function noteCell(note) {
    const td = el('td', 'lv-long');
    if (!note) {
        td.append(dash());
        return td;
    }

    td.append(clipped(note.text));
    if (note.by) td.append(el('small', 'ta-note-by', note.by));
    return td;
}

document.addEventListener('DOMContentLoaded', function () {
    initLvPage({
        dataId: 'timeAdjustmentData',
        haystack: function (row) { return row.name + ' ' + row.id + ' ' + row.type; },
        cells: function (row) {
            return [
                el('td', 'lv-name', row.name),
                cell(null, el('span', 'lv-type', row.type)),
                dataCell(row, row.original, 'original'),
                dataCell(row, row.requested, 'requested'),
                cell('lv-long', clipped(row.reason)),
                el('td', 'lv-date', row.submitted || '—'),
                statusCell(row),
                noteCell(row.hrNotes),
                noteCell(row.monNotes),
                noteCell(row.apNotes),
            ];
        },
    });
});
