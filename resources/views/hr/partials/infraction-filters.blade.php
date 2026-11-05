{{--
    Infractions: status filter, Excel download and department pills, shown under the company tabs.
    Included from tracker-head, so $pageUrl, $keep, $company come from there; $status,
    $departments, $department, $deptTotal from the infractions page.
--}}
@php
    $statusLabels = ['' => 'All', 'pending' => 'Pending', 'Served' => 'Served', 'Void' => 'Void'];
    $exportUrl = route('hr.infractions.export', array_filter(
        array_merge($keep, ['company' => $company, 'department' => null]),
        fn ($v) => $v !== null && $v !== ''
    ));
@endphp

<div class="hr-pills hr-pills--bar" aria-label="Status">
    <span class="hr-muted hr-small">Status</span>
    @foreach ($statusLabels as $value => $label)
        @php $selected = (string) $status === $value; @endphp
        <a href="{{ $pageUrl(['status' => $value, 'department' => null]) }}"
           aria-current="{{ $selected ? 'true' : 'false' }}"
           class="hr-pill {{ $selected ? 'active' : '' }}">{{ $label }}</a>
    @endforeach

    <a href="{{ $exportUrl }}" class="hr-btn hr-btn-ghost hr-btn-sm hr-pills-end" title="Download {{ $company }} to Excel (all departments)">
        <i class="fa-solid fa-download"></i> Excel
    </a>
</div>

@if ($departments->isNotEmpty())
    <div class="hr-pills" role="tablist" aria-label="Department">
        <a href="{{ $pageUrl(['department' => null]) }}"
           role="tab" aria-selected="{{ $department === null ? 'true' : 'false' }}"
           class="hr-pill {{ $department === null ? 'active' : '' }}">
            All
            <span class="hr-count">{{ number_format($deptTotal) }}</span>
        </a>
        @foreach ($departments as $dept)
            @php $selected = $department === $dept['id']; @endphp
            <a href="{{ $pageUrl(['department' => $dept['id']]) }}"
               role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}"
               class="hr-pill {{ $selected ? 'active' : '' }}">
                {{ $dept['name'] }}
                <span class="hr-count">{{ number_format($dept['matches']) }}</span>
            </a>
        @endforeach
    </div>
@endif
