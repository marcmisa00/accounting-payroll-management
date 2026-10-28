// Shared by the HSO and site transfer pages (hso.js, transfer.js): status chip,
// IT / HR / Accounting acknowledgement trail, and Accounting's acknowledge button.
// The server decides whether an application can be acknowledged; this file only
// asks for it and redraws the row with the trail the server returns.

import { el } from './lv-tabs.js';

const STATUS_LABELS = {
    approved: 'Approved',
    disapproved: 'Disapproved',
    cancelled: 'Cancelled',
    pending: 'Pending',
    other: 'Other',
};

const ACK_ICONS = {
    IT: 'fa-solid fa-desktop ap-ack-it',
    HR: 'fa-solid fa-user-tie ap-ack-hr',
    Accounting: 'fa-solid fa-credit-card ap-ack-acc',
};

export function statusCell(row) {
    const td = el('td', 'lv-status');
    const chip = el('span', 'lv-chip chip-' + row.status, STATUS_LABELS[row.status] || 'Other');

    if (row.flagged) {
        const star = el('span', 'lv-star', '*');
        star.title = 'This status is marked with * in HRIS';
        chip.append(star);
    }

    td.append(chip);
    if (row.statusBy) td.append(el('small', null, row.statusBy));

    return td;
}

export function rowClass(row) {
    return row.tone ? 'ap-row-' + row.tone : '';
}

export function ackCell(row) {
    const td = el('td', 'lv-long ap-ack-cell');

    if (!row.acks.length) {
        const waiting = el('span', 'ap-ack-waiting');
        waiting.append(el('i', 'fa-regular fa-clock'), ' Awaiting acknowledgement');
        td.append(waiting);
        return td;
    }

    const ul = el('ul', 'ap-ack');
    row.acks.forEach(function (a) {
        const li = el('li');
        li.append(el('i', ACK_ICONS[a.dept] || 'fa-solid fa-check'));

        const body = el('span');
        body.append(el('strong', null, ACK_ICONS[a.dept] ? a.dept + ' Department' : a.dept));
        const meta = [a.by, a.time].filter(Boolean).join(' · ');
        if (meta) body.append(el('small', null, meta));

        li.append(body);
        ul.append(li);
    });
    td.append(ul);

    return td;
}

export function actionCell(row) {
    const td = el('td', 'ap-action-col');
    if (!row.canAck) return td;

    const btn = el('button', 'ap-ack-btn');
    btn.type = 'button';
    btn.dataset.ack = row.appId;
    btn.title = 'Acknowledge as Accounting';
    btn.setAttribute('aria-label', 'Acknowledge application of ' + row.name);
    btn.append(el('i', 'fa-solid fa-thumbs-up'));
    td.append(btn);

    return td;
}

function adjustDue(tab, by) {
    const badge = tab && tab.querySelector('.ap-due');
    if (!badge) return;
    const n = Math.max(0, (parseInt(badge.textContent, 10) || 0) + by);
    badge.textContent = n;
    badge.hidden = n === 0;
}

/**
 * Click on 👍 → confirm → POST → redraw the acknowledgement and action cells.
 * rows: { "dept-key": { rows: [...] } } from the page JSON (updated in place).
 */
export function initAcknowledge(rows, label, cellIndex) {
    const root = document.querySelector('.ap');
    if (!root) return;

    root.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-ack]');
        if (!btn || btn.disabled) return;

        const tr = btn.closest('tr');
        const [key, i] = tr.dataset.row.split(':');
        const row = rows[key].rows[Number(i)];

        if (!window.confirm('Acknowledge this ' + label + ' for ' + row.name + '?')) return;

        btn.disabled = true;
        btn.classList.add('is-busy');

        let res;
        let body = {};
        try {
            res = await fetch(root.dataset.ackUrl.replace('__ID__', encodeURIComponent(btn.dataset.ack)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': root.dataset.csrf, Accept: 'application/json' },
                credentials: 'same-origin',
            });
            body = await res.json().catch(function () { return {}; });
        } catch (err) {
            res = null;
        }

        // Done, or someone else already acknowledged it: show the trail the server returned
        if (res && (res.ok || res.status === 409) && body.acks) {
            row.acks = body.acks;
            row.canAck = false;
            row.tone = 'acked';
            tr.className = rowClass(row);
            tr.cells[cellIndex].replaceWith(ackCell(row));
            tr.lastElementChild.replaceWith(actionCell(row));

            const deptTab = document.querySelector('.lv-dept-tabs [data-dept="' + CSS.escape(key) + '"]');
            const pane = deptTab && deptTab.closest('[data-company-pane]');
            adjustDue(deptTab, -1);
            adjustDue(pane && document.querySelector('.lv-company-tabs [data-company="' + CSS.escape(pane.dataset.companyPane) + '"]'), -1);

            if (!res.ok) window.alert(body.message);
            return;
        }

        btn.disabled = false;
        btn.classList.remove('is-busy');
        window.alert((body && body.message) || (res && res.status === 419
            ? 'Your session has expired. Reload the page and try again.'
            : 'The application could not be acknowledged. Please try again.'));
    });
}

export function pageRows(dataId) {
    try {
        return JSON.parse(document.getElementById(dataId).textContent || '{}');
    } catch (e) {
        return {};
    }
}
