
document.addEventListener('DOMContentLoaded', function () {

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
