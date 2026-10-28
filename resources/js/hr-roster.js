// HR Roster list + employee profile (view only). Filtering happens on the
// server through the GET form #hrForm; this file only drives the interface.

document.addEventListener('DOMContentLoaded', () => {
    initRoster();
    initProfile();
});

function initRoster() {
    const form = document.getElementById('hrForm');
    if (!form) return;

    // Search as you type, like the HR portal (submits after a short pause)
    const search = document.getElementById('hrSearch');
    let timer;
    search?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => form.requestSubmit(), 400);
    });
    if (search?.value) {
        search.focus();
        search.setSelectionRange(search.value.length, search.value.length);
    }

    // Column filter popovers: fixed to their header cell so the
    // horizontally scrolling table wrapper does not clip them
    let openPopover = null;

    const place = () => {
        if (!openPopover) return;
        const rect = openPopover.closest('th').getBoundingClientRect();
        const width = openPopover.offsetWidth;
        openPopover.style.top = `${rect.bottom + 6}px`;
        openPopover.style.left = `${Math.min(Math.max(8, rect.left), window.innerWidth - width - 8)}px`;
    };

    const close = () => {
        if (openPopover) openPopover.hidden = true;
        openPopover = null;
    };

    document.querySelectorAll('[data-popover]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const pop = document.getElementById(btn.dataset.popover);
            const wasOpen = pop === openPopover;
            close();
            if (wasOpen) return;
            pop.hidden = false;
            openPopover = pop;
            place();
            pop.querySelector('input:not([type=hidden]):not(:disabled), select:not(:disabled)')?.focus();
        });
    });

    document.addEventListener('click', (e) => {
        if (openPopover && !openPopover.contains(e.target)) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, { passive: true });
    document.getElementById('hrTableWrap')?.addEventListener('scroll', place, { passive: true });

    document.querySelectorAll('.hr-popover').forEach((pop) => {
        // Status: pick one option and apply
        pop.querySelectorAll('[data-value]').forEach((opt) => {
            opt.addEventListener('click', () => {
                pop.querySelector('input[type=hidden]').value = opt.dataset.value;
                form.requestSubmit();
            });
        });

        // Dates: Month / Year / Range; inputs of the other modes are disabled so they are not sent
        pop.querySelectorAll('[data-mode]').forEach((tab) => {
            tab.addEventListener('click', () => {
                const mode = tab.dataset.mode;
                pop.querySelector('[data-mode-input]').value = mode;
                pop.querySelectorAll('[data-mode]').forEach((t) => t.classList.toggle('is-active', t === tab));
                pop.querySelectorAll('[data-panel]').forEach((panel) => {
                    const on = panel.dataset.panel === mode;
                    panel.hidden = !on;
                    panel.querySelectorAll('input, select').forEach((el) => (el.disabled = !on));
                });
                place();
            });
        });

        pop.querySelector('[data-clear]')?.addEventListener('click', () => {
            pop.querySelectorAll('input, select').forEach((el) => (el.value = ''));
            form.requestSubmit();
        });
    });
}

function initProfile() {
    // Previous / next employee with the arrow keys
    const pager = document.getElementById('epfPager');
    if (pager) {
        document.addEventListener('keydown', (e) => {
            if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
            if (e.target.closest('input, textarea, select, [contenteditable]')) return;
            if (!document.getElementById('epfPoints')?.hidden) return;
            const link = pager.querySelector(`a[data-key="${e.key}"]`);
            if (link) {
                e.preventDefault();
                window.location.href = link.href;
            }
        });
    }

    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(btn.dataset.copy);
                btn.classList.add('is-copied');
                setTimeout(() => btn.classList.remove('is-copied'), 1200);
            } catch (e) {}
        });
    });

    // Early Out month picker (all months are already on the page)
    const eo = document.getElementById('epfEarlyOut');
    if (eo) {
        const months = JSON.parse(eo.dataset.months);
        eo.querySelector('select').addEventListener('change', (e) => {
            const { credits, used } = months[e.target.value];
            const pct = credits > 0 ? Math.min(100, Math.round((used / credits) * 100)) : 0;
            eo.querySelector('[data-eo-bar]').style.width = `${pct}%`;
            eo.querySelector('[data-eo-meta]').textContent = `${used} of ${credits} used`;
        });
    }

    // Points breakdown modal
    const modal = document.getElementById('epfPoints');
    if (modal) {
        const setOpen = (open) => {
            modal.hidden = !open;
            document.body.style.overflow = open ? 'hidden' : '';
        };
        document.querySelectorAll('[data-open="epfPoints"]').forEach((b) => b.addEventListener('click', () => setOpen(true)));
        modal.querySelector('[data-close]').addEventListener('click', () => setOpen(false));
        modal.addEventListener('click', (e) => {
            if (e.target === modal) setOpen(false);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.hidden) setOpen(false);
        });
    }
}
