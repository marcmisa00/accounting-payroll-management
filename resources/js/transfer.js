// Site Transfer page: tabs and search come from lv-tabs.js; status, acknowledgements
// and the acknowledge button from applications.js. This file only draws one row.

import { initLvPage, el, dash, clipped, cell } from './lv-tabs.js';
import { statusCell, ackCell, actionCell, rowClass, initAcknowledge, pageRows } from './applications.js';

const ACK_COLUMN = 9;

document.addEventListener('DOMContentLoaded', function () {
    initLvPage({
        dataId: 'transferData',
        haystack: function (row) { return row.name + ' ' + row.id; },
        rowClass: rowClass,
        cells: function (row) {
            return [
                el('td', 'lv-id', row.id),
                el('td', 'lv-name', row.name),
                el('td', 'lv-date', row.start || '—'),
                el('td', 'lv-date', row.end || 'Indefinite'),
                cell(null, row.from ? el('span', null, row.from) : dash()),
                cell(null, row.to ? el('span', 'lv-type', row.to) : dash()),
                cell('lv-long', clipped(row.reason)),
                el('td', 'lv-date', row.applied || '—'),
                statusCell(row),
                ackCell(row),
                actionCell(row),
            ];
        },
    });

    initAcknowledge(pageRows('transferData'), 'transfer application', ACK_COLUMN);
});
