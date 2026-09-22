@extends('layouts.app')

@section('content')

@if (session('success'))
    <script>alert(@json(session('success')));</script>
@endif
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css/show-payroll.css') }}">
<!-- Loading Overlay -->
<div class="download-loading-overlay" id="downloadLoadingOverlay">
    <div class="download-loading-box">
        <div class="spinner"></div>
        <p id="downloadStatus">Generating Download...</p>
        <p class="sub-text">Please wait, this may take a moment. Do not refresh the page.</p>
    </div>
</div>

<div class="centered-container">
    <div class="content-panel">
        <div class="panel-heading">
            <!-- Breadcrumb -->
            <div class="breadcrumb-section">
                <a href="{{ route('payroll.manage') }}"><i class="fa fa-arrow-left"></i> BACK</a> |
                <i class="fa fa-calendar"></i> PAYROLL PERIOD
                ({{ $payroll->periodfrom->format('M d, Y') }} - {{ $payroll->periodto->format('M d, Y') }})
            </div>

            <!-- Search Form -->
            <form method="GET" action="{{ route('payroll.show', $payroll) }}" class="search-section" onsubmit="return performSearch()">
                <span style="font-weight: bold;">Search:</span>
                <select name="searchColumn" id="columnSelect" class="custom-input custom-select" style="width: 150px; font-style: italic;">
                    <option value="all" @selected($searchColumn == 'all')>All Columns</option>
                    <option value="1" @selected($searchColumn == '1')>Emp ID</option>
                    <option value="2" @selected($searchColumn == '2')>Employee Name</option>
                    <option value="3" @selected($searchColumn == '3')>Setup</option>
                </select>

                <input type="text" name="search" id="searchInput" class="custom-input" placeholder="🔍 Search..." style="width: 200px;" value="{{ $searchTerm }}">

                <button type="submit" class="btn btn-primary" style="border-radius: 25px; padding: 6px 15px;">
                    <i class="fa fa-search"></i>
                </button>
                @if (!empty($searchTerm))
                    <a href="{{ route('payroll.show', $payroll) }}" class="reset-btn" style="border-radius: 25px; padding: 6px 15px;" title="Clear Search">
                        <i class="fa fa-id-badge"></i>
                    </a>
                @endif
            </form>
        </div>

        <div class="panel-body">
            <!-- Company Tabs -->
           <ul class="payroll-tabs company-tabs" id="companyTabs">
                @foreach ($companies as $index => $company)
                   <li class="{{ $index === 0 ? 'active' : '' }}">
                        <a class="company-tab"
                        href="#company-{{ $company['code'] }}"
                        data-tab-target="company-{{ $company['code'] }}">
                            {{ $company['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="payroll-tab-content company-tab-content" style="margin-top: 10px;">
                @foreach ($companies as $companyIndex => $company)
                    <div id="company-{{ $company['code'] }}" class="tab-pane{{ $companyIndex === 0 ? ' active' : '' }}">

                        <!-- Per-company action buttons -->
                        <div class="action-buttons">
                            @if ($company['posted'] === 0 && $company['notposted'] === 0)
                                {{-- No payroll records --}}
                            @elseif ($company['notposted'] > 0)
                                <form method="POST" action="{{ route('payroll.postPayslip', $payroll) }}" style="display:inline"
                                      onsubmit="return confirm('Do you wish to post payslip?');">
                                    @csrf
                                    <input type="hidden" name="company" value="{{ $company['code'] }}">
                                    <button type="submit" class="btn btn-primary">POST PAYSLIP</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('payroll.undoPostPayslip', $payroll) }}" style="display:inline"
                                      onsubmit="return confirm('Do you wish to undo post?');">
                                    @csrf
                                    <input type="hidden" name="company" value="{{ $company['code'] }}">
                                    <button type="submit" class="btn btn-warning">UNDO POST</button>
                                </form>
                            @endif

                            <!-- Download dropdown -->
                            <div class="btn-group">
                                <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-download"></i> Download <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li class="dropdown-header"><i class="fa fa-file-text-o"></i> PAYSLIP DOWNLOAD</li>
                                    <li><a href="/downloadAllPayslips.php?period={{ $payroll->id }}&company={{ $company['code'] }}&format=pdf" target="_blank" class="download-link">
                                        <i class="fa fa-file-pdf-o" style="color: #dc3545;"></i> Download All Payslips (PDF ZIP)
                                    </a></li>
                                    <li><a href="/downloadAllPayslipsExcel.php?period={{ $payroll->id }}&company={{ $company['code'] }}&format=excel" target="_blank" class="download-link">
                                        <i class="fa fa-file-excel-o" style="color: #28a745;"></i> Download All Payslips (Excel)
                                    </a></li>
                                    <li class="dropdown-divider"></li>
                                    <li class="dropdown-header"><i class="fa fa-table"></i> PAYROLL DOWNLOAD</li>
                                    <li><a href="/downloadDepartmentPayrollExcel.php?period={{ $payroll->id }}&company={{ $company['code'] }}" target="_blank" class="download-link">
                                        <i class="fa fa-file-excel-o" style="color: #17a2b8;"></i> Download All Departments Payroll
                                    </a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Department Tabs -->
                        <ul class="payroll-tabs dept-tabs" id="deptTabs-{{ $company['code'] }}">
                            @foreach ($company['departments'] as $deptIndex => $dept)
                                @php $deptSlug = preg_replace('/[^A-Za-z0-9]/', '', $dept['name']); @endphp
                                <li class="{{ $deptIndex === 0 ? 'active' : '' }} {{ $dept['has_search_match'] ? 'has-search-indicator' : '' }}">
                                    <a class="dept-tab" href="#dept-{{ $company['code'] }}-{{ $deptSlug }}" data-tab-target="dept-{{ $company['code'] }}-{{ $deptSlug }}">
                                        {{ $dept['name'] }}
                                        @if ($dept['has_search_match'])
                                            <span class="search-indicator" title="Search results found in this department"></span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        <div class="payroll-tab-content dept-tab-content" style="margin-top: 10px;">
                            @foreach ($company['departments'] as $deptIndex => $dept)
                                @php $deptSlug = preg_replace('/[^A-Za-z0-9]/', '', $dept['name']); @endphp
                                <div id="dept-{{ $company['code'] }}-{{ $deptSlug }}" class="tab-pane{{ $deptIndex === 0 ? ' active' : '' }}">
                                    <div class="panel-body">
                                        <table class="table table-bordered-bottom-only">
                                            <thead>
                                                <tr>
                                                    <th class="table-head-1">No.</th>
                                                    <th class="table-head-1">Emp ID</th>
                                                    <th class="table-head">Employee Name</th>
                                                    <th class="table-head-1">Company</th>
                                                    <th class="table-head-1">Setup</th>
                                                    <th class="table-head-2">Addons</th>
                                                    <th class="table-head-2">Total Gross</th>
                                                    <th class="table-head-2">Total Deductions</th>
                                                    <th class="table-head-2">Net Pay</th>
                                                    <th class="table-head-1">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($dept['employees'] as $x => $employee)
                                                    <tr>
                                                        <td class="table-cont-1" style="background-color: {{ $employee['bg_color'] }};">{{ $x + 1 }}.</td>
                                                        <td class="table-cont-1" style="background-color: {{ $employee['bg_color'] }};">{{ $employee['idno'] }}</td>
                                                        <td class="table-cont" style="background-color: {{ $employee['bg_color'] }};"><strong>{{ $employee['name'] }}</strong></td>
                                                        <td class="table-cont-1" style="background-color: {{ $employee['bg_color'] }};">{{ $company['code'] }}</td>
                                                        <td class="table-cont-1" style="background-color: {{ $employee['bg_color'] }};">
                                                            @if ($employee['location'] === 'OS')
                                                                <span class="label label-primary label-sm">{{ $employee['location'] }}</span>
                                                            @else
                                                                <span class="label label-warning label-sm">{{ $employee['location'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="table-cont-2" style="background-color: {{ $employee['bg_color'] }};">{{ number_format($employee['addons'], 2) }}</td>
                                                        <td class="table-cont-2" style="background-color: {{ $employee['bg_color'] }};">{{ number_format($employee['totalpay'], 2) }}</td>
                                                        <td class="table-cont-2" style="background-color: {{ $employee['bg_color'] }};">{{ number_format($employee['deductions'], 2) }}</td>
                                                        <td class="table-cont-2" style="background-color: {{ $employee['bg_color'] }};">{{ number_format($employee['netpay'], 2) }}</td>
                                                        <td class="table-cont-1" style="background-color: {{ $employee['bg_color'] }};">
                                                            <a href="/?editpayroll&idno={{ $employee['idno'] }}&period={{ $payroll->id }}&company={{ $company['code'] }}" class="btn btn-primary btn-xs" title="Edit Payroll">
                                                                <i class="fa fa-pencil"></i>
                                                            </a>
                                                            @if ($employee['payroll_id'])
                                                                @if ($employee['salary_type'] === 'Rated')
                                                                    <a href="/payslipRated.php?id={{ $employee['payroll_id'] }}" class="btn btn-warning btn-xs" title="View Payslip" target="_blank"><i class="fa fa-eye"></i></a>
                                                                    <a href="/exporttopdfRated.php?id={{ $employee['payroll_id'] }}&idno={{ $employee['idno'] }}&period={{ $payroll->id }}&company={{ $company['code'] }}" class="btn btn-success btn-xs" title="Export Payslip" target="_blank"><i class="fa fa-download"></i></a>
                                                                @elseif ($employee['salary_type'] === 'Fixed')
                                                                    <a href="/payslip.php?id={{ $employee['payroll_id'] }}" class="btn btn-warning btn-xs" title="View Payslip" target="_blank"><i class="fa fa-eye"></i></a>
                                                                    <a href="/exporttopdfFixed.php?id={{ $employee['payroll_id'] }}&idno={{ $employee['idno'] }}&period={{ $payroll->id }}&company={{ $company['code'] }}" class="btn btn-success btn-xs" title="Export Payslip" target="_blank"><i class="fa fa-download"></i></a>
                                                                @elseif ($employee['salary_type'] === 'Daily')
                                                                    <a href="/payslipDaily.php?id={{ $employee['payroll_id'] }}" class="btn btn-warning btn-xs" title="View Payslip" target="_blank"><i class="fa fa-eye"></i></a>
                                                                    <a href="/exporttopdfDaily.php?id={{ $employee['payroll_id'] }}&idno={{ $employee['idno'] }}&period={{ $payroll->id }}&company={{ $company['code'] }}" class="btn btn-success btn-xs" title="Export Payslip" target="_blank"><i class="fa fa-download"></i></a>
                                                                @endif
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="10" align="center" style="color: var(--sub-color); padding: 24px;">No record found!</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
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
</script>
<script>
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
</script>
@endsection