// Shared by the Leaves & Benefits list pages (leaves.js, time-adjustments.js):
// company → department tabs, tables drawn on demand from a JSON block, and a
// search that filters to matching rows in any tab. Each page supplies only
// how one row is drawn and which fields are searched.

const CLIP = 80; // characters shown before a "more" toggle on long text

export function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

export function dash() {
    return el('span', 'lv-none', '—');
}

// Long text: clipped with a "more"/"less" toggle. Always textContent, never HTML.
export function clipped(text) {
    if (!text) return dash();

    const value = String(text).trim();
    if (value.length <= CLIP) return el('span', 'lv-text', value);

    const wrap = el('span', 'lv-text');
    const body = el('span', null, value.slice(0, CLIP).trimEnd() + '…');
    const toggle = el('button', 'lv-more', 'more');
    toggle.type = 'button';

    toggle.addEventListener('click', function () {
        const open = toggle.textContent === 'less';
        body.textContent = open ? value.slice(0, CLIP).trimEnd() + '…' : value;
        toggle.textContent = open ? 'more' : 'less';
    });

    wrap.append(body, ' ', toggle);
    return wrap;
}

// Table cell wrapping a single node
export function cell(className, child) {
    const td = el('td', className);
    td.append(child);
    return td;
}

/**
 * options.dataId   id of the <script type="application/json"> block
 * options.cells    function (row) → array of <td> nodes for one row
 * options.haystack function (row) → text the search matches against
 */
export function initLvPage(options) {
    const dataEl = document.getElementById(options.dataId);
    if (!dataEl) return;

    let data = {};
    try {
        data = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        console.error('Page data could not be read.', e);
        return;
    }

    const rendered = new Set();

    /* ================================
     * Drawing a department table
     * ================================ */

    function renderDept(key) {
        if (rendered.has(key) || !data[key]) return;

        const tbody = document.querySelector('[data-dept-body="' + CSS.escape(key) + '"]');
        if (!tbody) return;

        const fragment = document.createDocumentFragment();

        data[key].rows.forEach(function (row, index) {
            const tr = el('tr');
            tr.dataset.row = key + ':' + index;
            tr.append.apply(tr, options.cells(row));
            fragment.append(tr);
        });

        tbody.append(fragment);
        rendered.add(key);
        filterRows(key);
    }

    /* ================================
     * Filtering (driven by the search below)
     * ================================ */

    // "key:index" of every matching row; null while the search box is empty
    let matchSet = null;

    function filterRows(key) {
        const tbody = document.querySelector('[data-dept-body="' + CSS.escape(key) + '"]');
        if (!tbody) return;

        tbody.querySelectorAll('tr[data-row]').forEach(function (tr) {
            tr.hidden = matchSet !== null && !matchSet.has(tr.dataset.row);
        });
    }

    // Tab badge shows the match count while filtering, the original total otherwise
    function setTabCount(tab, n) {
        const badge = tab.querySelector('.lv-count');
        if (!badge) return;
        if (badge.dataset.total === undefined) badge.dataset.total = badge.textContent;
        badge.textContent = n === null ? badge.dataset.total : n.toLocaleString();
    }

    /* ================================
     * Tabs
     * ================================ */

    function setActive(buttons, active) {
        buttons.forEach(function (b) {
            const on = b === active;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function showCompany(code) {
        const tab = document.querySelector('.lv-company-tabs [data-company="' + CSS.escape(code) + '"]');
        if (!tab) return;

        setActive(document.querySelectorAll('.lv-company-tabs .lv-tab'), tab);

        document.querySelectorAll('[data-company-pane]').forEach(function (pane) {
            pane.classList.toggle('is-active', pane.dataset.companyPane === code);
        });

        // Draw the department that is currently open inside this company
        const pane = document.querySelector('[data-company-pane="' + CSS.escape(code) + '"]');
        const activeDept = pane && pane.querySelector('.lv-dept-tabs .lv-tab.is-active');
        if (activeDept) renderDept(activeDept.dataset.dept);
    }

    function showDept(key) {
        const tab = document.querySelector('.lv-dept-tabs [data-dept="' + CSS.escape(key) + '"]');
        if (!tab) return;

        const companyPane = tab.closest('[data-company-pane]');

        setActive(companyPane.querySelectorAll('.lv-dept-tabs .lv-tab'), tab);

        companyPane.querySelectorAll('[data-dept-pane]').forEach(function (pane) {
            pane.classList.toggle('is-active', pane.dataset.deptPane === key);
        });

        renderDept(key);
    }

    document.querySelectorAll('.lv-company-tabs .lv-tab').forEach(function (tab) {
        tab.addEventListener('click', function () { showCompany(tab.dataset.company); });
    });

    document.querySelectorAll('.lv-dept-tabs .lv-tab').forEach(function (tab) {
        tab.addEventListener('click', function () { showDept(tab.dataset.dept); });
    });

    // First visible department
    const firstCompany = document.querySelector('.lv-company-tabs .lv-tab.is-active');
    if (firstCompany) showCompany(firstCompany.dataset.company);

    /* ================================
     * Search: filter to matching rows, then jump across tabs
     * ================================ */

    const root = document.querySelector('.lv');
    const noResults = document.getElementById('lvNoResults');
    const input = document.getElementById('lvSearch');
    const count = document.getElementById('lvSearchCount');
    const prev = document.getElementById('lvPrev');
    const next = document.getElementById('lvNext');
    if (!input) return;

    // Flat index of every row, in tab order (company tabs, then department tabs)
    const index = [];
    document.querySelectorAll('.lv-dept-tabs .lv-tab').forEach(function (tab) {
        const key = tab.dataset.dept;
        const dept = data[key];
        if (!dept) return;

        dept.rows.forEach(function (row, i) {
            index.push({
                key: key,
                company: dept.company,
                i: i,
                haystack: options.haystack(row).toLowerCase(),
            });
        });
    });

    let matches = [];
    let current = -1;
    let debounce = null;
    let lastTerm = '';

    function clearHighlight() {
        document.querySelectorAll('.lv-table tr.is-match').forEach(function (tr) {
            tr.classList.remove('is-match');
        });
    }

    // Hide non-matching rows, and the department/company tabs left with no matches
    function applyFilter(filtering) {
        matchSet = filtering ? new Set(matches.map(function (m) { return m.key + ':' + m.i; })) : null;

        const perDept = {};
        const perCompany = {};
        matches.forEach(function (m) {
            perDept[m.key] = (perDept[m.key] || 0) + 1;
            perCompany[m.company] = (perCompany[m.company] || 0) + 1;
        });

        document.querySelectorAll('.lv-dept-tabs .lv-tab').forEach(function (tab) {
            const n = perDept[tab.dataset.dept] || 0;
            tab.hidden = filtering && !n;
            setTabCount(tab, filtering ? n : null);
        });
        document.querySelectorAll('.lv-company-tabs .lv-tab').forEach(function (tab) {
            const n = perCompany[tab.dataset.company] || 0;
            tab.hidden = filtering && !n;
            setTabCount(tab, filtering ? n : null);
        });

        rendered.forEach(filterRows);

        const empty = filtering && !matches.length;
        root.classList.toggle('no-results', empty);
        noResults.hidden = !empty;
    }

    function updateCount() {
        const term = input.value.trim();

        if (!term) {
            count.textContent = '';
        } else if (!matches.length) {
            count.textContent = 'No match';
        } else {
            count.textContent = (current + 1) + ' of ' + matches.length;
        }

        prev.disabled = next.disabled = matches.length < 2;
        input.classList.toggle('no-match', term !== '' && !matches.length);
    }

    function jumpTo(i) {
        if (!matches.length) return;

        current = (i + matches.length) % matches.length;
        const m = matches[current];

        showCompany(m.company);
        showDept(m.key);

        clearHighlight();

        const tr = document.querySelector('tr[data-row="' + CSS.escape(m.key + ':' + m.i) + '"]');
        if (tr) {
            tr.classList.add('is-match');
            tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        updateCount();
    }

    function normalized() {
        return input.value.trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function search() {
        const term = normalized();
        lastTerm = term;

        clearHighlight();

        // Every typed word must appear, in any order: "Juan Cruz" finds "Dela Cruz, Juan Pablo"
        const words = term ? term.split(' ') : [];

        matches = words.length
            ? index.filter(function (r) { return words.every(function (w) { return r.haystack.includes(w); }); })
            : [];
        current = -1;

        applyFilter(words.length > 0);

        if (matches.length) {
            jumpTo(0);
        } else {
            updateCount();
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(search, 200);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        clearTimeout(debounce);

        // New text (typed faster than the debounce) → search; same text → next/previous match
        if (normalized() !== lastTerm || current === -1) {
            search();
        } else {
            jumpTo(current + (e.shiftKey ? -1 : 1));
        }
    });

    next.addEventListener('click', function () { jumpTo(current + 1); });
    prev.addEventListener('click', function () { jumpTo(current - 1); });
}
