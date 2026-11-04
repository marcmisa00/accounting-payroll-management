
document.addEventListener('DOMContentLoaded', function () {

    // Opening Manage Payroll ends a "came from the dashboard" edit session (see payroll/edit)
    try {
        Object.keys(sessionStorage)
            .filter(function (key) { return key.indexOf('payrollEditReturn-') === 0; })
            .forEach(function (key) { sessionStorage.removeItem(key); });
    } catch (e) {}

    /*
     * ================================
     * POST / UNDO POST SELECTED COMPANIES
     * ================================
     */

    const bulkForm = document.getElementById('bulkPostForm');

    if (bulkForm) {
        const selectAll = document.getElementById('postSelectAll');
        const postBtn   = document.getElementById('postSelectedBtn');
        const undoBtn   = document.getElementById('undoSelectedBtn');
        const checks    = Array.from(document.querySelectorAll('.post-check:not(:disabled)'));

        // Checked companies that have payslips in the given state (data-pending / data-posted)
        const selectedWith = function (state) {
            return checks.filter(function (box) {
                return box.checked && Number(box.dataset[state]) > 0;
            });
        };

        const refresh = function () {
            const checkedCount = checks.filter(function (box) { return box.checked; }).length;
            const toPost = selectedWith('pending').length;
            const toUndo = selectedWith('posted').length;

            postBtn.disabled = toPost === 0;
            undoBtn.disabled = toUndo === 0;
            postBtn.querySelector('[data-count]').textContent = toPost ? '(' + toPost + ')' : '';
            undoBtn.querySelector('[data-count]').textContent = toUndo ? '(' + toUndo + ')' : '';

            selectAll.disabled      = checks.length === 0;
            selectAll.checked       = checks.length > 0 && checkedCount === checks.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checks.length;
        };

        selectAll.addEventListener('change', function () {
            checks.forEach(function (box) { box.checked = selectAll.checked; });
            refresh();
        });

        checks.forEach(function (box) { box.addEventListener('change', refresh); });

        bulkForm.addEventListener('submit', function (e) {
            const isUndo = e.submitter === undoBtn;
            const boxes  = selectedWith(isUndo ? 'posted' : 'pending');
            const names  = boxes.map(function (box) { return box.value; }).join(', ');

            if (!boxes.length || !confirm(isUndo
                ? 'Do you wish to undo post for ' + names + '?'
                : 'Do you wish to post payslip for ' + names + '?')) {
                e.preventDefault();
                return;
            }

            // Send only the companies this action applies to
            checks.forEach(function (box) {
                if (box.checked && boxes.indexOf(box) === -1) {
                    box.disabled = true;
                }
            });
        });

        refresh();
    }

    /*
     * ================================
     * COMPANY TABS
     * ================================
     */

    const companyTabs = document.querySelectorAll(
        '#companyTabs .company-tab'
    );

    const companyPanes = document.querySelectorAll(
        '.company-tab-content > .tab-pane'
    );

    companyTabs.forEach(function (tab) {

        tab.addEventListener('click', function (e) {
            e.preventDefault();

            const targetId = this.getAttribute('data-tab-target');

            // Remove active from all company tabs
            companyTabs.forEach(function (item) {
                item.parentElement.classList.remove('active');
            });

            // Hide all company panes
            companyPanes.forEach(function (pane) {
                pane.classList.remove('active');
            });

            // Activate clicked tab
            this.parentElement.classList.add('active');

            // Show selected company
            const targetPane = document.getElementById(targetId);

            if (targetPane) {
                targetPane.classList.add('active');
            }

            // Remember selected company
            localStorage.setItem(
                'activeCompanyTab',
                targetId
            );
        });
    });


    /*
     * ================================
     * DEPARTMENT TABS
     * ================================
     */

    document.querySelectorAll('.dept-tabs').forEach(function (tabContainer) {

        const tabs = tabContainer.querySelectorAll('.dept-tab');

        const companyCode = tabContainer.id.replace('deptTabs-', '');

        const storageKey = 'activeDeptTab-' + companyCode;

        tabs.forEach(function (tab) {

            tab.addEventListener('click', function (e) {
                e.preventDefault();

                const targetId = this.getAttribute('data-tab-target');

                // Remove active from department tabs
                tabs.forEach(function (item) {
                    item.parentElement.classList.remove('active');
                });

                // Find the department content belonging
                // to this company
                const companyPane = tabContainer.closest('.tab-pane');

                if (!companyPane) {
                    return;
                }

                const departmentPanes =
                    companyPane.querySelectorAll(
                        '.dept-tab-content > .tab-pane'
                    );

                // Hide all departments for this company
                departmentPanes.forEach(function (pane) {
                    pane.classList.remove('active');
                });

                // Activate clicked department
                this.parentElement.classList.add('active');

                const targetPane =
                    document.getElementById(targetId);

                if (targetPane) {
                    targetPane.classList.add('active');
                }

                // Remember department
                localStorage.setItem(
                    storageKey,
                    targetId
                );
            });
        });
    });


    /*
     * ================================
     * RESTORE COMPANY TAB
     * ================================
     */

    const savedCompany =
        localStorage.getItem('activeCompanyTab');

    if (savedCompany) {

        const savedCompanyTab =
            document.querySelector(
                '#companyTabs [data-tab-target="' +
                savedCompany +
                '"]'
            );

        if (savedCompanyTab) {
            savedCompanyTab.click();
        }
    }


    /*
     * ================================
     * RESTORE DEPARTMENT TAB
     * ================================
     */

    document.querySelectorAll('.dept-tabs').forEach(function (tabContainer) {

        const companyCode =
            tabContainer.id.replace('deptTabs-', '');

        const storageKey =
            'activeDeptTab-' + companyCode;

        const savedDept =
            localStorage.getItem(storageKey);

        if (!savedDept) {
            return;
        }

        const savedDeptTab =
            tabContainer.querySelector(
                '[data-tab-target="' +
                savedDept +
                '"]'
            );

        if (savedDeptTab) {
            savedDeptTab.click();
        }
    });

});

/*
 * ================================
 * SEARCH: PINPOINT THE MATCHES
 * ================================
 * The server already filters each department to matching employees.
 * This opens the company + department of the first match, scrolls to it,
 * highlights the matched text and lists where every match is.
 * Registered after the tab-restore listener above, so it wins over it.
 */
document.addEventListener('DOMContentLoaded', function () {

    const page = document.getElementById('payrollShow');
    const results = document.getElementById('searchResults');
    const term = page ? (page.dataset.searchTerm || '').trim() : '';

    if (!page || !results || term === '') {
        return;
    }

    const column = page.dataset.searchColumn || 'all';

    // Collect every department pane that has matching rows
    const locations = [];

    document.querySelectorAll('.company-tab-content > .tab-pane').forEach(function (companyPane) {
        const companyTab = document.querySelector('#companyTabs [data-tab-target="' + companyPane.id + '"]');

        companyPane.querySelectorAll('.dept-tab-content > .tab-pane').forEach(function (deptPane) {
            const rows = deptPane.querySelectorAll('tr.emp-row');

            if (rows.length === 0) {
                return;
            }

            const deptTab = companyPane.querySelector('.dept-tab[data-tab-target="' + deptPane.id + '"]');

            locations.push({
                companyTab: companyTab,
                deptTab: deptTab,
                company: companyTab ? companyTab.textContent.trim() : '',
                dept: deptTab ? deptTab.textContent.trim() : '',
                rows: rows,
            });
        });
    });

    // Wrap the matched text in <mark> (text nodes only, no HTML from the term)
    const needle = term.toLowerCase();
    const cells = column === 'all' ? ['1', '2', '3'] : [column];

    function highlight(cell) {
        const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT);
        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(function (node) {
            const text = node.nodeValue;
            const at = text.toLowerCase().indexOf(needle);

            if (at === -1) {
                return;
            }

            const match = node.splitText(at);
            match.splitText(needle.length);

            const mark = document.createElement('mark');
            mark.className = 'search-hit';
            mark.textContent = match.nodeValue;
            match.parentNode.replaceChild(mark, match);
        });
    }

    locations.forEach(function (loc) {
        loc.rows.forEach(function (row) {
            cells.forEach(function (c) {
                row.querySelectorAll('[data-hl="' + c + '"]').forEach(highlight);
            });
        });
    });

    function jumpTo(loc) {
        if (loc.companyTab) loc.companyTab.click();
        if (loc.deptTab) loc.deptTab.click();

        const row = loc.rows[0];

        row.classList.remove('search-focus');
        void row.offsetWidth; // restart the flash animation
        row.classList.add('search-focus');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Results bar
    const total = locations.reduce(function (sum, loc) { return sum + loc.rows.length; }, 0);
    const label = document.createElement('span');
    label.className = 'search-results-label';

    if (total === 0) {
        results.classList.add('is-empty');
        label.textContent = 'No employees match "' + term + '".';
        results.appendChild(label);
        results.hidden = false;
        return;
    }

    label.textContent = total + (total === 1 ? ' match' : ' matches') + ' for "' + term + '":';
    results.appendChild(label);

    locations.forEach(function (loc) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'search-results-loc';
        button.textContent = loc.company + ' › ' + loc.dept + ' (' + loc.rows.length + ')';
        button.addEventListener('click', function () { jumpTo(loc); });
        results.appendChild(button);
    });

    results.hidden = false;

    jumpTo(locations[0]);
});

function performSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.trim();

    if (searchTerm === '') {
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        url.searchParams.delete('searchColumn');
        window.location.href = url.toString();
        return false;
    }

    return true;
}

$("#searchInput").on("keypress", function (e) {
    if (e.which === 13) {
        $(this).closest('form').submit();
        return false;
    }
});

$(document).ready(function () {
    $('.download-link').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var link = $(this);
        var href = link.attr('href');
        var text = link.text().trim();

        $('#downloadStatus').text('Generating ' + text + '...');
        $('#downloadLoadingOverlay').css('display', 'flex');

        window.open(href, '_blank');

        setTimeout(function () {
            $('#downloadLoadingOverlay').fadeOut(500, function () {
                $('.btn-group').removeClass('open');
            });
        }, 3000);
    });
});
