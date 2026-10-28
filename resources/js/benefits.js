// Salary & Benefits pages (list, edit form, history). Interface only: the server
// validates and decides what is saved; the change list shown before saving is
// just a preview of what the form will send.

document.addEventListener('DOMContentLoaded', () => {
    initList();
    initForm();
    initSnapshots();
});

function initList() {
    // Search as you type (submits after a short pause), like the HR roster
    const search = document.querySelector('[data-autosearch]');
    if (search) {
        let timer;
        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => search.form.requestSubmit(), 400);
        });
        if (search.value) {
            search.focus();
            search.setSelectionRange(search.value.length, search.value.length);
        }
    }

    // Whole row opens the employee (the name link stays the keyboard target)
    document.querySelectorAll('tr[data-href]').forEach((tr) => {
        tr.addEventListener('click', (e) => {
            if (e.target.closest('a, button')) return;
            window.location.href = tr.dataset.href;
        });
    });
}

const money = (v) => {
    const n = parseFloat(String(v).replace(/[, ]/g, ''));
    return Number.isFinite(n) ? n.toFixed(2) : String(v).trim();
};

function initForm() {
    const form = document.getElementById('bfForm');
    if (!form) return;

    /* ---------- Account number: keep / change / remove ---------- */
    const action = form.querySelector('[data-account-action]');
    const view = form.querySelector('[data-account-view]');
    const edit = form.querySelector('[data-account-edit]');
    const input = edit.querySelector('input');
    const mask = form.querySelector('[data-account-mask]');
    const removeBtn = view.querySelector('[data-account="remove"]');
    const undoBtn = view.querySelector('[data-account="keep"]');

    const setAccount = (mode) => {
        action.value = mode;
        view.hidden = mode === 'change';
        edit.hidden = mode !== 'change';
        input.disabled = mode !== 'change';
        if (mode !== 'change') input.value = '';
        mask.classList.toggle('is-removed', mode === 'remove');
        if (removeBtn) removeBtn.hidden = mode === 'remove';
        if (undoBtn) undoBtn.hidden = mode !== 'remove';
        if (mode === 'change') input.focus();
    };

    form.querySelectorAll('[data-account]').forEach((btn) => {
        btn.addEventListener('click', () => setAccount(btn.dataset.account));
    });

    /* ---------- Reminder: a new rate needs a new effective date ---------- */
    const salary = form.querySelector('#salary');
    const date = form.querySelector('#effective_date');
    const hint = document.getElementById('bfDateHint');
    const checkDate = () => {
        const rateChanged = salary.dataset.original !== '' && money(salary.value) !== money(salary.dataset.original);
        hint.hidden = !(rateChanged && date.value === date.dataset.original);
    };
    salary.addEventListener('input', checkDate);
    date.addEventListener('input', checkDate);
    checkDate();

    // Tidy amounts to 2 decimals when leaving the field
    form.querySelectorAll('[data-money]').forEach((el) => {
        el.addEventListener('blur', () => {
            if (el.value.trim() !== '' && /^[\d,\s]+(\.\d*)?$/.test(el.value.trim())) el.value = money(el.value);
        });
    });

    /* ---------- Confirm with the list of changes ---------- */
    const modal = document.getElementById('bfConfirm');
    const list = modal.querySelector('[data-confirm-list]');
    let confirmed = false;

    const changes = () => {
        const out = [];

        form.querySelectorAll('input[data-original], select[data-original]').forEach((el) => {
            const isMoney = el.hasAttribute('data-money');
            const before = isMoney ? (el.dataset.original === '' ? '0.00' : money(el.dataset.original)) : el.dataset.original;
            const after = isMoney ? (el.value.trim() === '' ? '0.00' : money(el.value)) : el.value;
            if (before !== after) out.push([el.dataset.label, before || 'blank', after || 'blank']);
        });

        const type = form.querySelector('[data-original][role="radiogroup"]');
        const picked = form.querySelector('input[name="salary_type"]:checked');
        if (picked && picked.value !== type.dataset.original) {
            out.push([type.dataset.label, type.dataset.original, picked.value]);
        }

        if (action.value === 'change') out.push(['Bank Account', null, 'New number entered']);
        if (action.value === 'remove') out.push(['Bank Account', null, 'Will be removed']);

        return out;
    };

    form.addEventListener('submit', (e) => {
        if (confirmed) return;
        e.preventDefault();

        const items = changes();
        list.replaceChildren();

        if (!items.length) {
            const li = document.createElement('li');
            li.textContent = 'No changes. Saving will not write anything.';
            list.append(li);
        }

        items.forEach(([label, before, after]) => {
            const li = document.createElement('li');
            const f = document.createElement('span');
            f.className = 'bf-change-field';
            f.textContent = label;
            li.append(f);
            if (before !== null) {
                const o = document.createElement('span');
                o.className = 'bf-change-old';
                o.textContent = before;
                const arrow = document.createElement('i');
                arrow.className = 'fa-solid fa-arrow-right-long';
                li.append(o, arrow);
            }
            const n = document.createElement('span');
            n.className = 'bf-change-new';
            n.textContent = after;
            li.append(n);
            list.append(li);
        });

        modal.hidden = false;
        modal.querySelector('[data-confirm-ok]').focus();
    });

    const close = () => { modal.hidden = true; };
    modal.querySelector('[data-confirm-cancel]').addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) close(); });

    modal.querySelector('[data-confirm-ok]').addEventListener('click', (e) => {
        confirmed = true;
        e.currentTarget.disabled = true;
        form.requestSubmit();
    });

    // Leaving with unsaved edits (previous / next / back)
    const dirty = () => changes().length > 0;
    document.querySelectorAll('[data-nav], .bf-crumbs a, .bf-actions a').forEach((a) => {
        a.addEventListener('click', (e) => {
            if (dirty() && !window.confirm('You have unsaved changes. Leave without saving?')) e.preventDefault();
        });
    });
}

function initSnapshots() {
    document.querySelectorAll('[data-toggle-snapshot]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const snap = btn.closest('.bf-entry').querySelector('.bf-snapshot');
            snap.hidden = !snap.hidden;
            btn.setAttribute('aria-expanded', snap.hidden ? 'false' : 'true');
        });
    });
}
