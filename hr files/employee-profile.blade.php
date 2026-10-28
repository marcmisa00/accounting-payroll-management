@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-profile.css') }}">
@endpush

<div class="epf-page">
    @section('title', 'Employee Profile')

    @php
        $p          = $d['profile'];
        $det        = $d['details'];
        $isRegular  = strtoupper($det->status ?? '') === 'REGULAR';
        $setupValue = strtoupper((string) ($det->location ?? ''));
        $setupClass = match ($setupValue) {
            'WFH'    => 'hr-badge-warning',
            'HYBRID' => 'hr-badge-info',
            default  => 'hr-badge-success',
        };
    @endphp

    {{-- ==================== HEADER ==================== --}}
    <header class="epf-hero hr-card">
        {{-- Row 1: breadcrumb + previous / next + actions --}}
        @php
            $listUrl    = route('employees', $listContext);
            $profileUrl = fn ($id) => route('employees.profile', ['id' => $id] + $listContext);
        @endphp
        <div class="epf-hero__bar">
            <div class="epf-hero__nav">
                <nav class="epf-crumbs" aria-label="Breadcrumb">
                    {{-- Straight to the list (with its filters), not history.back(),
                         which would step back through previously viewed profiles --}}
                    <a href="{{ $listUrl }}" class="hr-icon-btn epf-crumbs__back" aria-label="Back to employees">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                        </svg>
                    </a>
                    <a href="{{ $listUrl }}" class="epf-crumbs__link">Employees</a>
                    <span class="epf-crumbs__sep" aria-hidden="true">/</span>
                    <span class="epf-crumbs__current" aria-current="page">Profile</span>
                </nav>

                @if ($pager)
                    {{-- Previous / next employee in the list; ← / → keys work too --}}
                    <div class="epf-pager"
                         x-data
                         x-on:keydown.window="
                            if ($event.altKey || $event.ctrlKey || $event.metaKey || $event.shiftKey) return;
                            if ($event.target.closest('input, textarea, select, [contenteditable], [role=dialog], .add-emp-panel')) return;
                            const link = $refs[$event.key === 'ArrowLeft' ? 'prev' : ($event.key === 'ArrowRight' ? 'next' : '')];
                            if (link && link.href) { $event.preventDefault(); window.location.href = link.href; }
                         ">
                        @if ($pager['prev'])
                            <a href="{{ $profileUrl($pager['prev']['id']) }}" x-ref="prev"
                               class="hr-icon-btn epf-pager__btn"
                               title="Previous: {{ $pager['prev']['name'] }} (←)"
                               aria-label="Previous employee: {{ $pager['prev']['name'] }}">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                                </svg>
                            </a>
                        @else
                            <span class="hr-icon-btn epf-pager__btn is-disabled" aria-disabled="true" title="First in the list">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                                </svg>
                            </span>
                        @endif

                        <span class="epf-pager__pos" aria-live="polite">
                            <strong>{{ number_format($pager['position']) }}</strong> of {{ number_format($pager['total']) }}
                        </span>

                        @if ($pager['next'])
                            <a href="{{ $profileUrl($pager['next']['id']) }}" x-ref="next"
                               class="hr-icon-btn epf-pager__btn"
                               title="Next: {{ $pager['next']['name'] }} (→)"
                               aria-label="Next employee: {{ $pager['next']['name'] }}">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                </svg>
                            </a>
                        @else
                            <span class="hr-icon-btn epf-pager__btn is-disabled" aria-disabled="true" title="Last in the list">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                </svg>
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="epf-hero__actions">
                <button type="button"
                        onclick="window.location.href='{{ route('employee.movement', ['idno' => $idno]) }}'"
                        class="hr-btn hr-btn-sm">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                    </svg>
                    Movement
                </button>
                <button type="button"
                        wire:click="$dispatch('openPanel', { component: 'employees.apply-leave', arguments: { profileId: {{ (int) $p->id }} } })"
                        class="hr-btn hr-btn-sm">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    </svg>
                    Apply Leave
                </button>
                <button type="button"
                        wire:click="$dispatch('openPanel', { component: 'employees.apply-eeo', arguments: { profileId: {{ (int) $p->id }} } })"
                        class="hr-btn hr-btn-sm">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Apply EEO
                </button>
                <button type="button"
                        wire:click="$dispatch('openPanel', { component: 'employees.edit-employee', arguments: { profileId: {{ (int) $p->id }} } })"
                        class="hr-btn hr-btn-primary hr-btn-sm">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                    </svg>
                    Edit Employee
                </button>
            </div>
        </div>

        {{-- Row 2: identity --}}
        <div class="epf-hero__identity">
            <img class="epf-hero__avatar"
                 wire:key="avatar-{{ $idno }}"
                 src="{{ $d['image'] }}"
                 alt="{{ $d['fullName'] }}"
                 onerror="this.onerror=null; this.src='{{ \App\Helpers\EmployeePhoto::defaultUrl() }}';">

            <div class="epf-hero__who">
                <div class="epf-hero__name-row">
                    <h1 class="epf-hero__name">{{ $d['fullName'] }}</h1>
                    <span class="hr-badge {{ $isRegular ? 'hr-badge-success' : 'hr-badge-warning' }}">
                        {{ $det->status ?? 'Unknown' }}
                    </span>
                </div>
                <p class="epf-hero__role">
                    {{ $d['jobtitle'] ?: 'No job title' }}
                    @if ($d['department'])
                        <span class="epf-hero__sep" aria-hidden="true">·</span> {{ $d['department'] }}
                    @endif
                </p>
                <div class="epf-hero__meta">
                    <span class="epf-hero__idno">ID {{ $idno }}</span>
                    @if ($d['companyname'])
                        <span class="epf-hero__meta-item">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                            </svg>
                            {{ $d['companyname'] }}
                        </span>
                    @endif
                    @if ($p->comp_email)
                        <x-copy-text :value="$p->comp_email" label="email" class="epf-hero__meta-item">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                            {{ $p->comp_email }}
                        </x-copy-text>
                    @endif
                    @if ($p->phone_number)
                        <x-copy-text :value="$p->phone_number" label="phone number" class="epf-hero__meta-item">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                            </svg>
                            {{ $p->phone_number }}
                        </x-copy-text>
                    @endif
                </div>
            </div>
        </div>

        {{-- Row 3: key numbers --}}
        <dl class="epf-hero__stats">
            <div class="epf-stat">
                <dt class="epf-stat__k">Length of service</dt>
                <dd class="epf-stat__v">{{ $d['years'] }}y {{ $d['months'] }}m {{ $d['days'] }}d</dd>
            </div>
            <div class="epf-stat">
                <dt class="epf-stat__k">Shift</dt>
                <dd class="epf-stat__v">
                    {{ $d['shift'] ?: '—' }}
                    @if ($d['isGraveyard'])
                        <span class="hr-badge hr-badge-neutral">Graveyard</span>
                    @endif
                </dd>
            </div>
            <div class="epf-stat">
                <dt class="epf-stat__k">Work setup</dt>
                <dd class="epf-stat__v">
                    <span class="hr-badge {{ $setupClass }}">{{ $setupValue ?: 'OS' }}</span>
                    <span class="epf-stat__sub">{{ $det->work_area ?? '' ?: '—' }}</span>
                </dd>
            </div>
            <div class="epf-stat">
                <dt class="epf-stat__k">Points ({{ $d['currentYear'] }})</dt>
                <dd class="epf-stat__v">
                    {{ $d['points'] }}
                    <button type="button" wire:click="togglePointsModal" class="epf-stat__link">View breakdown</button>
                </dd>
            </div>
        </dl>
    </header>

    {{-- ==================== BODY ====================
         One grid for sidebar + main so cards side by side share a row
         and always have the same height (areas in employee-profile.css). --}}
    <div class="epf-body">

        {{-- ============ SIDEBAR ============ --}}
        <aside class="epf-side">
            <section class="epf-side__card epf-area-personal">
                <h3 class="epf-side__title">Personal</h3>
                <dl class="epf-kv">
                    <div class="epf-kv__row">
                        <dt>Gender</dt>
                        <dd>{{ match ($p->sex) { 'M' => 'Male', 'F' => 'Female', default => '—' } }}</dd>
                    </div>
                    <div class="epf-kv__row">
                        <dt>Birthdate</dt>
                        <dd>
                            {{ $p->birthdate ? date('M d, Y', strtotime($p->birthdate)) : '—' }}
                            @if ($d['age'] !== null)
                                <span class="epf-kv__sub">{{ $d['age'] }} yrs old</span>
                            @endif
                        </dd>
                    </div>
                    <div class="epf-kv__row">
                        <dt>Civil status</dt>
                        <dd>{{ match ($p->civilstatus) { 'S' => 'Single', 'M' => 'Married', 'W' => 'Widowed', default => $p->civilstatus ?: '—' } }}</dd>
                    </div>
                </dl>
            </section>

            <section class="epf-side__card epf-area-contact">
                <h3 class="epf-side__title">Contact</h3>
                <dl class="epf-kv epf-kv--stacked">
                    <div class="epf-kv__row">
                        <dt>Email</dt>
                        <dd class="epf-kv__break">
                            @if ($p->comp_email)
                                <x-copy-text :value="$p->comp_email" label="email">{{ $p->comp_email }}</x-copy-text>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="epf-kv__row">
                        <dt>Phone</dt>
                        <dd>
                            @if ($p->phone_number)
                                <x-copy-text :value="$p->phone_number" label="phone number">{{ $p->phone_number }}</x-copy-text>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="epf-kv__row">
                        <dt>Address</dt>
                        <dd>{{ $p->address ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="epf-side__card epf-area-assignment">
                <h3 class="epf-side__title">Assignment</h3>
                <dl class="epf-kv">
                    <div class="epf-kv__row">
                        <dt>Company</dt>
                        <dd>{{ $d['companyname'] ?: '—' }}</dd>
                    </div>
                    <div class="epf-kv__row">
                        <dt>Department</dt>
                        <dd>{{ $d['department'] ?: '—' }}</dd>
                    </div>
                    <div class="epf-kv__row">
                        <dt>Location</dt>
                        <dd>{{ $det->work_area ?? '' ?: '—' }}</dd>
                    </div>
                    <div class="epf-kv__row">
                        <dt>Setup</dt>
                        <dd>{{ $det->location ?? '' ?: '—' }}</dd>
                    </div>
                </dl>
            </section>
        </aside>

        {{-- ============ MAIN ============ --}}
        <main class="epf-main">

            {{-- Employment timeline --}}
            <section class="epf-widget epf-area-timeline">
                <header class="epf-widget__head">
                    <h3 class="epf-widget__title">Employment Timeline</h3>
                </header>

                <div class="epf-timeline">
                    <div class="epf-timeline__item">
                        <div class="epf-timeline__marker"></div>
                        <div class="epf-timeline__label">Date Hired</div>
                        <div class="epf-timeline__value">
                            {{ $d['details'] && $d['details']->dateofhired ? date('F d, Y', strtotime($d['details']->dateofhired)) : '—' }}
                        </div>
                    </div>
                    <div class="epf-timeline__item">
                        <div class="epf-timeline__marker"></div>
                        <div class="epf-timeline__label">Regularization</div>
                        <div class="epf-timeline__value">
                            {{ $d['details'] && $d['details']->dateofregular ? date('F d, Y', strtotime($d['details']->dateofregular)) : '—' }}
                        </div>
                    </div>
                    <div class="epf-timeline__item">
                        <div class="epf-timeline__marker"></div>
                        <div class="epf-timeline__label">LC Effectivity</div>
                        <div class="epf-timeline__value">
                            {{ $d['details'] && $d['details']->dateleaveeffective ? date('F d, Y', strtotime($d['details']->dateleaveeffective)) : '—' }}
                        </div>
                    </div>
                    <div class="epf-timeline__item">
                        <div class="epf-timeline__marker"></div>
                        <div class="epf-timeline__label">Full Time</div>
                        <div class="epf-timeline__value">
                            {{ $d['details'] && $d['details']->dateoffulltime ? date('F d, Y', strtotime($d['details']->dateoffulltime)) : '—' }}
                        </div>
                    </div>
                </div>
            </section>

            {{-- Leave credits --}}
            <section class="epf-widget epf-area-leave">
                <header class="epf-widget__head">
                    <h3 class="epf-widget__title">Leave Credits</h3>
                    <span class="epf-widget__hint">
                        @if (!empty($d['periodFrom']) || !empty($d['periodTo']))
                            {{ $d['periodFrom'] ?: '—' }} → {{ $d['periodTo'] ?: '—' }}
                        @else
                            {{ date('Y') }} allocation
                        @endif
                    </span>
                </header>

                @php
                    $leave = $d['leave'];
                    $num = fn ($v) => $v === null || $v === '' ? 0 : (float) $v;
                    $cards = [
                        'Vacation Leave' => [$num($leave->vacationleave ?? 0), $num($leave->vlused ?? 0)],
                        'Sick Leave'     => [$num($leave->sickleave ?? 0),     $num($leave->slused ?? 0)],
                        'PTO'            => [$num($leave->pto ?? 0),           $num($leave->ptoused ?? 0)],
                        'Birthday Leave' => [$num($leave->bdayleave ?? 0),     $num($leave->blp_used ?? 0)],
                        'SPL'            => [$num($leave->spl ?? 0),           $num($leave->spl_used ?? 0)],
                    ];
                @endphp

                <div class="epf-leave">
                    @foreach ($cards as $name => [$total, $used])
                        @php
                            $remaining = $total - $used;
                            $pct = $total > 0 ? min(100, round(($used / $total) * 100)) : 0;
                        @endphp
                        <div class="epf-leave__item">
                            <div class="epf-leave__head">
                                <span class="epf-leave__name">{{ $name }}</span>
                                <span class="epf-leave__count"><strong>{{ $remaining }}</strong> left</span>
                            </div>
                            <div class="epf-bar">
                                <div class="epf-bar__fill" style="width: {{ $pct }}%"></div>
                            </div>
                            <div class="epf-leave__meta">{{ $used }} of {{ $total }} used</div>
                        </div>
                    @endforeach

                    {{-- Early Out --}}
                    <div class="epf-leave__item">
                        @php
                            $eoCredits = (float) ($leave->{$eoMonth . '_earlyout'} ?? 0);
                            $eoUsed    = (float) ($leave->{$eoMonth . '_eo_used'} ?? 0);
                            $eoPct     = $eoCredits > 0 ? min(100, round(($eoUsed / $eoCredits) * 100)) : 0;
                        @endphp
                        <div class="epf-leave__head">
                            <span class="epf-leave__name">Early Out</span>
                            <select class="epf-leave__select" wire:change="setEoMonth($event.target.value)">
                                @foreach (['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'] as $m)
                                    <option value="{{ $m }}" @selected($eoMonth === $m)>{{ strtoupper($m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="epf-bar">
                            <div class="epf-bar__fill" style="width: {{ $eoPct }}%"></div>
                        </div>
                        <div class="epf-leave__meta">{{ $eoUsed }} of {{ $eoCredits }} used</div>
                    </div>
                </div>
            </section>

            {{-- ========== ISLANDS AFTER LEAVE CREDITS — TWO COLUMNS ========== --}}
            <div class="epf-grid-2col">
                {{-- Benefits --}}
                <section class="epf-widget epf-area-benefits">
                    <header class="epf-widget__head">
                        <h3 class="epf-widget__title">Government & Benefits</h3>
                    </header>

                    @php $b = $d['benefits']; @endphp
                    <div class="epf-grid">
                        <div class="epf-field">
                            <span class="epf-field__k">Life Insurance</span>
                            <span class="epf-field__v">{{ $b && $b->insurance ? date('M d, Y', strtotime($b->insurance)) : '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">HMO Effectivity</span>
                            <span class="epf-field__v">{{ $b && $b->hmo ? date('M d, Y', strtotime($b->hmo)) : '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">SSS No.</span>
                            <span class="epf-field__v">{{ $b->sss ?? '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">TIN No.</span>
                            <span class="epf-field__v">{{ $b->tin ?? '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">PhilHealth</span>
                            <span class="epf-field__v">{{ $b->phic ?? '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">Pag-IBIG</span>
                            <span class="epf-field__v">{{ $b->hdmf ?? '—' }}</span>
                        </div>
                    </div>
                </section>

                {{-- Contract & Referral — 2-ROW HEADER/VALUE TABLE --}}
                <section class="epf-widget epf-area-contract">
                    <header class="epf-widget__head">
                        <h3 class="epf-widget__title">Contract & Referral</h3>
                    </header>

                    <div class="epf-table">
                        {{-- Row 1: Contract (3 columns for alignment) --}}
                        <div class="epf-table__row epf-table__row--3">
                            <div class="epf-table__cell">
                                <span class="epf-table__head">Regular</span>
                                <span class="epf-table__val">
                                    {{ $d['contract']->regular ?? '—' }}
                                    @if ($d['contract'] && $d['contract']->regulardate)
                                        <em>{{ date('M d, Y', strtotime($d['contract']->regulardate)) }}</em>
                                    @endif
                                </span>
                            </div>
                            <div class="epf-table__cell">
                                <span class="epf-table__head">Probationary</span>
                                <span class="epf-table__val">
                                    {{ $d['contract']->probationary ?? '—' }}
                                    @if ($d['contract'] && $d['contract']->probationarydate)
                                        <em>{{ date('M d, Y', strtotime($d['contract']->probationarydate)) }}</em>
                                    @endif
                                </span>
                            </div>
                            <div class="epf-table__cell"></div>
                        </div>

                        {{-- Row 2: Referral (3 columns) --}}
                        <div class="epf-table__row epf-table__row--3">
                            <div class="epf-table__cell">
                                <span class="epf-table__head">Referred By</span>
                                <span class="epf-table__val">
                                    {{ $d['referral'] ? trim($d['referral']->firstname . ' ' . $d['referral']->lastname) : '—' }}
                                </span>
                            </div>
                            <div class="epf-table__cell">
                                <span class="epf-table__head">Referral Type</span>
                                <span class="epf-table__val">
                                    @if ($d['referral'] && $d['referral']->referral_type)
                                        @php $isMed = strtolower($d['referral']->referral_type) === 'med'; @endphp
                                        <span class="epf-tag {{ $isMed ? 'epf-tag--teal' : 'epf-tag--amber' }}">
                                            {{ $d['referral']->referral_type }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="epf-table__cell">
                                <span class="epf-table__head">Effectivity</span>
                                <span class="epf-table__val">
                                    @if ($d['referral'] && $d['referral']->effectivity && !in_array($d['referral']->effectivity, ['0001-01-01', '0000-00-00']))
                                        {{ date('M d, Y', strtotime($d['referral']->effectivity)) }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Checklist --}}
                <section class="epf-widget epf-area-checklist">
                    <header class="epf-widget__head">
                        <h3 class="epf-widget__title">Checklist & IDs</h3>
                        @if ($d['checklist'] && $d['checklist']->status)
                            <span class="epf-tag {{ strtolower($d['checklist']->status) === 'cleared' ? 'epf-tag--green' : 'epf-tag--amber' }}">
                                {{ $d['checklist']->status }}
                            </span>
                        @endif
                    </header>

                    @php $c = $d['checklist']; @endphp
                    <div class="epf-grid">
                        <div class="epf-field">
                            <span class="epf-field__k">Date Oriented</span>
                            <span class="epf-field__v">{{ $c && $c->dateoriented ? date('M d, Y', strtotime($c->dateoriented)) : '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">Temp ID</span>
                            <span class="epf-field__v">{{ $c->releasedtempid ?? '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">Permanent ID</span>
                            <span class="epf-field__v">{{ $c->releasedpermanentid ?? '—' }}</span>
                        </div>
                    </div>
                </section>

                {{-- Emergency Contact --}}
                <section class="epf-widget epf-area-emergency">
                    <header class="epf-widget__head">
                        <h3 class="epf-widget__title">Emergency Contact</h3>
                    </header>

                    @php
                        $person = in_array($d['profile']->contactperson, ['', 'N/A', '-']) ? '' : $d['profile']->contactperson;
                        $rel    = in_array($d['profile']->relationship, ['', 'N/A', '-']) ? '' : $d['profile']->relationship;
                        $num    = in_array($d['profile']->contactnumber, ['', 'N/A', '-']) ? '' : $d['profile']->contactnumber;
                    @endphp

                    <div class="epf-grid">
                        <div class="epf-field">
                            <span class="epf-field__k">Contact Person</span>
                            <span class="epf-field__v">{{ $person ?: '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">Relationship</span>
                            <span class="epf-field__v">{{ $rel ?: '—' }}</span>
                        </div>
                        <div class="epf-field">
                            <span class="epf-field__k">Contact Number</span>
                            <span class="epf-field__v">{{ $num ?: '—' }}</span>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    {{-- ==================== POINTS MODAL ==================== --}}
    @if ($showPointsModal)
        <div class="epf-modal-backdrop" wire:click.self="togglePointsModal">
            <div class="epf-modal">
                <header class="epf-modal__head">
                    <div>
                        <h3>Points Breakdown</h3>
                        <span class="epf-modal__sub">{{ $d['currentYear'] }} · {{ $d['points'] }} total</span>
                    </div>
                    <button type="button" class="epf-modal__close" wire:click="togglePointsModal" aria-label="Close">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </header>

                <div class="epf-modal__body">
                    @php
                        $translations = [
                            "12" => "Absent with proper called-in",
                            "13" => "Absent with proper called-in, Invalid reason",
                            "65" => "Forgot to clock in (first shift) and failed to submit form and Over-break",
                            "15" => "Late within 15 minutes",
                            "16" => "Late 16 minutes and up with called-in",
                            "17" => "Late 16 minutes and up without called-in",
                            "22" => "Forgot to clock in (first shift) and failed to submit form",
                            "19" => "Over-Break (lunch)",
                            "63" => "Forgot to clock in/out (Lunch) w/ non-work related reason",
                            "62" => "Absent w/ CI w/in 30mins prior shift & 15 mins after",
                            "66" => "Forgot to clock in (first shift) and failed to submit form and Missed Out/In (Lunch)",
                            "78" => "Sick leave w/o supporting documents",
                            "72" => "Government Sanction",
                            "67" => "Internet Outage",
                            "68" => "Natural Calamity",
                            "77" => "PC Problem",
                            "64" => "Power Outage",
                            "73" => "Transportation Issue",
                            "79" => "SL with proper CI, invalid reason",
                            "83" => "Server Down/HRIS unreachable",
                            "80" => "SL w/ CI w/in 30 mins prior shift & 15 mins",
                            "84" => "Required Duty"
                        ];

                        $byMonth = [];
                        foreach ($d['pointsRows'] as $row) {
                            $label = $translations[$row->offense] ?? $row->offense;
                            $month = date('F', strtotime($row->logindate));
                            $byMonth[$month][] = [
                                'label' => $label,
                                'points' => (float) $row->points,
                                'date' => $row->logindate,
                            ];
                        }
                        foreach ($byMonth as &$rows) {
                            usort($rows, fn ($a, $b) => $b['points'] <=> $a['points']);
                        }
                        unset($rows);
                    @endphp

                    @if (empty($byMonth))
                        <div class="epf-modal__empty">
                            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p>No points recorded for {{ $d['currentYear'] }}.</p>
                        </div>
                    @else
                        @foreach ($byMonth as $month => $rows)
                            <div class="epf-modal__month">
                                <h4>{{ $month }}</h4>
                                <ul>
                                    @foreach ($rows as $row)
                                        <li>
                                            <span class="epf-modal__pts">{{ number_format($row['points'], 1) }}</span>
                                            <span class="epf-modal__label">{{ $row['label'] }}</span>
                                            <span class="epf-modal__date">{{ date('M d', strtotime($row['date'])) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>