// Leave Credits and Monthly Leave Credits pages: tabs and search come from lv-tabs.js;
// this file draws one employee row, sorts by the first columns and shows the "Used"
// dates popup (only when the page has data-used-url; the monthly page shows plain numbers).

import { initLvPage, el, dash } from './lv-tabs.js';

const root = document.querySelector('.lc');
const usedUrl = (id) => root.dataset.usedUrl.replace('IDNO', encodeURIComponent(id));
const profileUrl = (id) => root.dataset.profileUrl.replace('IDNO', encodeURIComponent(id));

function td(className, content, sortValue) {
    const cell = el('td', className);
    if (content instanceof Node) cell.append(content);
    else if (content === null || content === undefined || content === '') cell.append(dash());
    else cell.textContent = content;
    if (sortValue !== undefined && sortValue !== null) cell.dataset.sort = sortValue;
    return cell;
}

function nameLink(row) {
    const a = el('a', 'lc-name');
    a.href = profileUrl(row.id);
    a.title = 'View profile';
    a.append(el('strong', null, row.last), ', ' + row.name);
    return a;
}

function leaveCells(row, leave) {
    let used = leave.used;
    if (root.dataset.usedUrl) {
        used = el('button', 'lc-used', String(leave.used));
        used.type = 'button';
        used.title = 'Click to view dates';
        used.dataset.id = row.id;
        used.dataset.type = leave.type;
    }

    return [
        td('num lc-credit lc-group-start', leave.credits),
        td('num', used),
        td('num ' + (leave.remaining <= 0 ? 'lc-out' : 'lc-ok'), String(leave.remaining)),
    ];
}

/* ================================
 * Sorting (first five columns)
 * ================================ */

function compare(a, b) {
    const na = Number(a);
    const nb = Number(b);
    if (a !== '' && b !== '' && !isNaN(na) && !isNaN(nb)) return na - nb;
    return a.localeCompare(b, undefined, { sensitivity: 'base' });
}

function initSorting() {
    document.querySelectorAll('.lc-table .lc-sortable').forEach((th) => {
        th.addEventListener('click', () => {
            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            const col = Number(th.dataset.sortCol);
            const asc = !th.classList.contains('asc');

            table.querySelectorAll('.lc-sortable').forEach((h) => h.classList.remove('asc', 'desc'));
            th.classList.add(asc ? 'asc' : 'desc');

            const value = (tr) => {
                const cell = tr.cells[col];
                return cell ? (cell.dataset.sort ?? cell.textContent.trim()) : '';
            };

            Array.from(tbody.rows)
                .sort((a, b) => (asc ? 1 : -1) * compare(value(a), value(b)))
                .forEach((tr) => tbody.append(tr));
        });
    });
}

/* ================================
 * "Used" dates popup
 * ================================ */

let popup = null;

function closePopup() {
    popup?.remove();
    popup = null;
}

function placePopup(anchor) {
    const rect = anchor.getBoundingClientRect();
    const width = popup.offsetWidth;
    let left = rect.right + 10;
    if (left + width > window.innerWidth - 10) left = Math.max(10, rect.left - width - 10);
    const top = Math.min(rect.top, window.innerHeight - popup.offsetHeight - 10);
    popup.style.left = `${left}px`;
    popup.style.top = `${Math.max(10, top)}px`;
}

async function openPopup(button) {
    closePopup();

    popup = el('div', 'lc-popup');
    popup.setAttribute('role', 'dialog');
    popup.append(el('em', 'lc-popup-muted', 'Loading…'));
    document.body.append(popup);
    placePopup(button);

    const mine = popup;
    let data;
    try {
        const res = await fetch(`${usedUrl(button.dataset.id)}?type=${encodeURIComponent(button.dataset.type)}`, {
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) throw new Error(res.status);
        data = await res.json();
    } catch (e) {
        data = { message: 'Could not load the dates. Please try again.' };
    }
    if (popup !== mine) return; // closed or replaced while loading

    popup.replaceChildren();
    if (data.title) popup.append(el('strong', null, data.title));
    if (data.period) popup.append(el('div', 'lc-popup-muted', data.period));

    if (data.message) {
        popup.append(el('em', 'lc-popup-muted', data.message));
    } else if (!data.dates.length) {
        popup.append(el('span', 'lc-popup-muted', 'No records'));
    } else {
        const list = el('ul');
        data.dates.forEach((d) => {
            const li = el('li', null, d.date + ' ');
            li.append(el('small', null, `(${d.remarks})`));
            list.append(li);
        });
        popup.append(list);
    }
    placePopup(button);
}

document.addEventListener('click', (e) => {
    const button = e.target.closest('.lc-used');
    if (button) {
        e.stopPropagation();
        openPopup(button);
    } else if (popup && !popup.contains(e.target)) {
        closePopup();
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePopup();
});
window.addEventListener('resize', closePopup);
document.addEventListener('scroll', (e) => {
    if (popup && !popup.contains(e.target)) closePopup();
}, { passive: true, capture: true });

/* ================================
 * Page
 * ================================ */

document.addEventListener('DOMContentLoaded', function () {
    initLvPage({
        dataId: 'leaveCreditData',
        haystack: (row) => `${row.last}, ${row.name} ${row.name} ${row.last} ${row.id}`,
        cells: (row) => {
            const cells = [
                td('lv-id', row.id),
                td(null, nameLink(row), `${row.last}, ${row.name}`),
                td('lv-date', row.eligibility, row.eligSort),
                td('lc-service', row.service, row.serviceSort),
                td('lv-date', row.period, row.periodSort),
            ];
            Object.values(row.leaves).forEach((leave) => cells.push(...leaveCells(row, leave)));
            if (row.tone) cells.forEach((c) => c.classList.add('lc-' + row.tone));
            return cells;
        },
    });

    initSorting();
});
