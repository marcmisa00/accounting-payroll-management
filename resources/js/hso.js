// HSO Application page: tabs and search come from lv-tabs.js; status, acknowledgements
// and the acknowledge button from applications.js. This file draws one row and the
// details popup (loaded from the server when opened).

import { initLvPage, el, dash, clipped, cell } from './lv-tabs.js';
import { statusCell, ackCell, actionCell, rowClass, initAcknowledge, pageRows } from './applications.js';

const ACK_COLUMN = 9;

function nameCell(row) {
    const td = el('td', 'lv-name');
    const btn = el('button', 'ap-info');
    btn.type = 'button';
    btn.dataset.details = row.appId;
    btn.title = 'View application details';
    btn.setAttribute('aria-label', 'View details for ' + row.name);
    btn.append(el('i', 'fa-solid fa-circle-info'));
    td.append(el('span', null, row.name), ' ', btn);
    return td;
}

/* ================================
 * Details popup
 * ================================ */

function field(label, value) {
    const div = el('div', 'ap-field');
    div.append(el('span', null, label));
    div.append(value instanceof Node ? value : el('strong', null, value || '—'));
    return div;
}

function section(title, fields) {
    const s = el('section', 'ap-section');
    s.append(el('h4', null, title));
    const grid = el('div', 'ap-grid');
    fields.filter(Boolean).forEach(function (f) { grid.append(f); });
    s.append(grid);
    return s;
}

function details(d) {
    const frag = document.createDocumentFragment();

    frag.append(section('Employee', [
        field('Name', d.name),
        field('Employee ID', d.idno),
        field('Department', d.department),
        field('Job Title', d.jobtitle),
        field('Contact', d.contact),
        d.type ? field('HSO Type', d.type) : null,
    ]));

    frag.append(section('Location & Schedule', [
        field('Address', d.address),
        field('Start Date', d.start),
        field('End Date', d.end),
        field('HSO Schedule', d.schedule),
    ]));

    frag.append(section('Internet & Work Setup', [
        field('Work Calls', d.workCall),
        field('Connection Type', d.connection),
        field('Primary ISP', d.isp),
        field('Primary Speed', d.speed),
        field('Backup Internet', d.backup),
        d.backupIsp ? field('Backup ISP', d.backupIsp) : null,
        d.backupSpeed ? field('Backup Speed', d.backupSpeed) : null,
    ]));

    const reason = el('section', 'ap-section');
    reason.append(el('h4', null, 'Reason for Request'));
    if (d.reasonType) {
        const medical = d.reasonType === 'Medical';
        const badge = el('span', 'lv-chip ' + (medical ? 'chip-disapproved' : 'chip-other'));
        badge.append(el('i', medical ? 'fa-solid fa-hospital' : 'fa-solid fa-laptop'), ' ' + d.reasonType);
        reason.append(badge);
    }
    reason.append(el('p', 'ap-reason', d.reason || '—'));
    frag.append(reason);

    const agreement = el('section', 'ap-section');
    agreement.append(el('h4', null, 'Signed Agreement'));
    if (d.agreement) {
        const a = el('a', 'ap-file');
        a.href = d.agreement.url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.append(el('i', 'fa-regular fa-file-pdf'), ' ' + d.agreement.name);
        agreement.append(a);
    } else {
        agreement.append(el('p', 'lv-none', 'No agreement attached'));
    }
    frag.append(agreement);

    return frag;
}

function initDetails() {
    const root = document.querySelector('.ap');
    const modal = document.getElementById('apModal');
    if (!root || !modal) return;

    const body = modal.querySelector('[data-modal-body]');
    const sub = modal.querySelector('[data-modal-sub]');
    let opener = null;

    const close = function () {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (opener) opener.focus();
    };

    root.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-details]');
        if (!btn) return;

        opener = btn;
        sub.textContent = 'Application #' + btn.dataset.details;
        body.replaceChildren(el('p', 'ap-loading', 'Loading…'));
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        modal.querySelector('[data-modal-close]').focus();

        try {
            const res = await fetch(root.dataset.showUrl.replace('__ID__', encodeURIComponent(btn.dataset.details)), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(res.status);
            const d = await res.json();
            sub.textContent = 'Application #' + d.id + ' · ' + d.name;
            body.replaceChildren(details(d));
        } catch (err) {
            body.replaceChildren(el('p', 'ap-loading', 'The details could not be loaded. Please try again.'));
        }
    });

    modal.querySelector('[data-modal-close]').addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
}

document.addEventListener('DOMContentLoaded', function () {
    initLvPage({
        dataId: 'hsoData',
        haystack: function (row) { return row.name + ' ' + row.id + ' ' + (row.type || ''); },
        rowClass: rowClass,
        cells: function (row) {
            return [
                el('td', 'lv-id', row.id),
                nameCell(row),
                cell(null, row.type ? el('span', 'lv-type', row.type) : dash()),
                el('td', 'lv-date', row.start || '—'),
                el('td', 'lv-date', row.end || '—'),
                cell('lv-long', clipped(row.reason)),
                cell('lv-long', clipped(row.schedule)),
                el('td', 'lv-date', row.applied || '—'),
                statusCell(row),
                ackCell(row),
                actionCell(row),
            ];
        },
    });

    initAcknowledge(pageRows('hsoData'), 'HSO application', ACK_COLUMN);
    initDetails();
});
