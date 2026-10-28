@extends('layouts.app')

@section('title', 'Employee Profile - Accounting Portal')
@section('page-title', 'Employee Profile')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
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

    // HRIS stores empty dates as NULL, 0000-00-00 or 0001-01-01
    $fmt = fn ($date, $format = 'M d, Y') => $date && !in_array($date, ['0000-00-00', '0001-01-01'], true)
        ? date($format, strtotime($date)) : '—';

    $listUrl    = route('hr.roster', $listContext);
    $profileUrl = fn ($idno) => route('hr.roster.show', ['idno' => $idno] + $listContext);
@endphp

<div class="epf">

    {{-- ==================== HEADER ==================== --}}
    <header class="epf-hero">
        {{-- Row 1: breadcrumb + previous / next --}}
        <div class="epf-hero__bar">
            <nav class="epf-crumbs" aria-label="Breadcrumb">
                {{-- Straight to the list (with its filters), not history.back(),
                     which would step back through previously viewed profiles --}}
                <a href="{{ $listUrl }}" class="hr-icon-btn" aria-label="Back to HR Roster">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <a href="{{ $listUrl }}" class="epf-crumbs__link">HR Roster</a>
                <span class="epf-crumbs__sep" aria-hidden="true">/</span>
                <span aria-current="page">Profile</span>
            </nav>

            @if ($pager)
                {{-- Previous / next employee in the list; ← / → keys work too --}}
                <div class="epf-pager" id="epfPager">
                    @if ($pager['prev'])
                        <a href="{{ $profileUrl($pager['prev']['idno']) }}" data-key="ArrowLeft" class="hr-icon-btn"
                           title="Previous: {{ $pager['prev']['name'] }} (←)" aria-label="Previous employee: {{ $pager['prev']['name'] }}">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    @else
                        <span class="hr-icon-btn is-disabled" aria-disabled="true" title="First in the list"><i class="fa-solid fa-chevron-left"></i></span>
                    @endif

                    <span class="epf-pager__pos" aria-live="polite">
                        <strong>{{ number_format($pager['position']) }}</strong> of {{ number_format($pager['total']) }}
                    </span>

                    @if ($pager['next'])
                        <a href="{{ $profileUrl($pager['next']['idno']) }}" data-key="ArrowRight" class="hr-icon-btn"
                           title="Next: {{ $pager['next']['name'] }} (→)" aria-label="Next employee: {{ $pager['next']['name'] }}">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    @else
                        <span class="hr-icon-btn is-disabled" aria-disabled="true" title="Last in the list"><i class="fa-solid fa-chevron-right"></i></span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Row 2: identity --}}
        <div class="epf-hero__identity">
            <div class="epf-hero__avatar" aria-hidden="true">{{ $d['initials'] }}</div>

            <div class="epf-hero__who">
                <div class="epf-hero__name-row">
                    <h1 class="epf-hero__name">{{ $d['fullName'] }}</h1>
                    <span class="hr-badge {{ $isRegular ? 'hr-badge-success' : 'hr-badge-warning' }}">{{ $det->status ?? 'Unknown' }}</span>
                </div>
                <p class="epf-hero__role">
                    {{ $d['jobtitle'] ?: 'No job title' }}
                    @if ($d['department'])
                        <span aria-hidden="true">·</span> {{ $d['department'] }}
                    @endif
                </p>
                <div class="epf-hero__meta">
                    <span class="epf-hero__idno">ID {{ $idno }}</span>
                    @if ($d['companyname'])
                        <span><i class="fa-regular fa-building"></i> {{ $d['companyname'] }}</span>
                    @endif
                    @if ($p->comp_email)
                        <button type="button" class="epf-copy" data-copy="{{ $p->comp_email }}" title="Copy email">
                            <i class="fa-regular fa-envelope"></i> {{ $p->comp_email }}
                        </button>
                    @endif
                    @if ($p->phone_number)
                        <button type="button" class="epf-copy" data-copy="{{ $p->phone_number }}" title="Copy phone number">
                            <i class="fa-solid fa-phone"></i> {{ $p->phone_number }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Row 3: key numbers --}}
        <dl class="epf-stats">
            <div class="epf-stat">
                <dt>Length of service</dt>
                <dd>{{ $d['years'] }}y {{ $d['months'] }}m {{ $d['days'] }}d</dd>
            </div>
            <div class="epf-stat">
                <dt>Shift</dt>
                <dd>
                    {{ $d['shift'] ?: '—' }}
                    @if ($d['isGraveyard'])
                        <span class="hr-badge hr-badge-neutral">Graveyard</span>
                    @endif
                </dd>
            </div>
            <div class="epf-stat">
                <dt>Work setup</dt>
                <dd>
                    <span class="hr-badge {{ $setupClass }}">{{ $setupValue ?: 'OS' }}</span>
                    <span class="epf-stat__sub">{{ $det->work_area ?: '—' }}</span>
                </dd>
            </div>
            <div class="epf-stat">
                <dt>Points ({{ $d['currentYear'] }})</dt>
                <dd>
                    {{ $d['points'] }}
                    <button type="button" class="epf-link" data-open="epfPoints">View breakdown</button>
                </dd>
            </div>
        </dl>
    </header>

    <div class="epf-body">

        {{-- ============ SIDEBAR ============ --}}
        <aside class="epf-side">
            <section class="epf-card">
                <h3 class="epf-card__title">Personal</h3>
                <dl class="epf-kv">
                    <div><dt>Gender</dt><dd>{{ match ($p->sex) { 'M' => 'Male', 'F' => 'Female', default => '—' } }}</dd></div>
                    <div>
                        <dt>Birthdate</dt>
                        <dd>
                            {{ $fmt($p->birthdate) }}
                            @if ($d['age'] !== null)
                                <span class="epf-kv__sub">{{ $d['age'] }} yrs old</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt>Civil status</dt><dd>{{ match ($p->civilstatus) { 'S' => 'Single', 'M' => 'Married', 'W' => 'Widowed', default => $p->civilstatus ?: '—' } }}</dd></div>
                </dl>
            </section>

            <section class="epf-card">
                <h3 class="epf-card__title">Contact</h3>
                <dl class="epf-kv epf-kv--stacked">
                    <div>
                        <dt>Email</dt>
                        <dd class="epf-break">
                            @if ($p->comp_email)
                                <button type="button" class="epf-copy" data-copy="{{ $p->comp_email }}" title="Copy email">{{ $p->comp_email }}</button>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd>
                            @if ($p->phone_number)
                                <button type="button" class="epf-copy" data-copy="{{ $p->phone_number }}" title="Copy phone number">{{ $p->phone_number }}</button>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div><dt>Address</dt><dd>{{ $p->address ?: '—' }}</dd></div>
                </dl>
            </section>

            <section class="epf-card">
                <h3 class="epf-card__title">Assignment</h3>
                <dl class="epf-kv">
                    <div><dt>Company</dt><dd>{{ $d['companyname'] ?: '—' }}</dd></div>
                    <div><dt>Department</dt><dd>{{ $d['department'] ?: '—' }}</dd></div>
                    <div><dt>Location</dt><dd>{{ $det->work_area ?: '—' }}</dd></div>
                    <div><dt>Setup</dt><dd>{{ $det->location ?: '—' }}</dd></div>
                </dl>
            </section>
        </aside>

        {{-- ============ MAIN ============ --}}
        <main class="epf-main">

            {{-- Employment timeline --}}
            <section class="epf-card">
                <h3 class="epf-card__title">Employment Timeline</h3>
                <div class="epf-timeline">
                    @foreach (['Date Hired' => $det->dateofhired, 'Regularization' => $det->dateofregular, 'LC Effectivity' => $det->dateleaveeffective, 'Full Time' => $det->dateoffulltime] as $label => $date)
                        <div class="epf-timeline__item">
                            <div class="epf-timeline__marker"></div>
                            <div class="epf-timeline__label">{{ $label }}</div>
                            <div class="epf-timeline__value">{{ $fmt($date, 'F d, Y') }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Leave credits --}}
            <section class="epf-card">
                <header class="epf-card__head">
                    <h3 class="epf-card__title">Leave Credits</h3>
                    <span class="epf-hint">
                        @if ($d['periodFrom'] || $d['periodTo'])
                            {{ $d['periodFrom'] ?: '—' }} → {{ $d['periodTo'] ?: '—' }}
                        @else
                            {{ $d['currentYear'] }} allocation
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
                    $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
                    $earlyOut = collect($months)->mapWithKeys(fn ($m) => [$m => [
                        'credits' => $num($leave->{$m . '_earlyout'} ?? 0),
                        'used'    => $num($leave->{$m . '_eo_used'} ?? 0),
                    ]]);
                    $eo = $earlyOut[$eoMonth];
                    $pct = fn ($used, $total) => $total > 0 ? min(100, round(($used / $total) * 100)) : 0;
                @endphp

                <div class="epf-leave">
                    @foreach ($cards as $name => [$total, $used])
                        <div class="epf-leave__item">
                            <div class="epf-leave__head">
                                <span class="epf-leave__name">{{ $name }}</span>
                                <span><strong>{{ $total - $used }}</strong> left</span>
                            </div>
                            <div class="epf-bar"><div class="epf-bar__fill" style="width: {{ $pct($used, $total) }}%"></div></div>
                            <div class="epf-leave__meta">{{ $used }} of {{ $total }} used</div>
                        </div>
                    @endforeach

                    {{-- Early Out: the month picker only changes what is shown --}}
                    <div class="epf-leave__item" id="epfEarlyOut" data-months='@json($earlyOut)'>
                        <div class="epf-leave__head">
                            <span class="epf-leave__name">Early Out</span>
                            <select class="epf-leave__select" aria-label="Early out month">
                                @foreach ($months as $m)
                                    <option value="{{ $m }}" @selected($eoMonth === $m)>{{ strtoupper($m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="epf-bar"><div class="epf-bar__fill" data-eo-bar style="width: {{ $pct($eo['used'], $eo['credits']) }}%"></div></div>
                        <div class="epf-leave__meta" data-eo-meta>{{ $eo['used'] }} of {{ $eo['credits'] }} used</div>
                    </div>
                </div>
            </section>

            <div class="epf-grid-2col">
                {{-- Benefits --}}
                <section class="epf-card">
                    <h3 class="epf-card__title">Government &amp; Benefits</h3>
                    @php $b = $d['benefits']; @endphp
                    <div class="epf-grid">
                        <div class="epf-field"><span>Life Insurance</span><strong>{{ $fmt($b->insurance ?? null) }}</strong></div>
                        <div class="epf-field"><span>HMO Effectivity</span><strong>{{ $fmt($b->hmo ?? null) }}</strong></div>
                        <div class="epf-field"><span>SSS No.</span><strong>{{ ($b->sss ?? '') ?: '—' }}</strong></div>
                        <div class="epf-field"><span>TIN No.</span><strong>{{ ($b->tin ?? '') ?: '—' }}</strong></div>
                        <div class="epf-field"><span>PhilHealth</span><strong>{{ ($b->phic ?? '') ?: '—' }}</strong></div>
                        <div class="epf-field"><span>Pag-IBIG</span><strong>{{ ($b->hdmf ?? '') ?: '—' }}</strong></div>
                    </div>
                </section>

                {{-- Contract & Referral --}}
                <section class="epf-card">
                    <h3 class="epf-card__title">Contract &amp; Referral</h3>
                    @php
                        $c = $d['contract'];
                        $r = $d['referral'];
                    @endphp
                    <div class="epf-grid epf-grid--3">
                        <div class="epf-field">
                            <span>Regular</span>
                            <strong>
                                {{ ($c->regular ?? '') ?: '—' }}
                                @if ($c && $fmt($c->regulardate) !== '—')
                                    <em>{{ $fmt($c->regulardate) }}</em>
                                @endif
                            </strong>
                        </div>
                        <div class="epf-field">
                            <span>Probationary</span>
                            <strong>
                                {{ ($c->probationary ?? '') ?: '—' }}
                                @if ($c && $fmt($c->probationarydate) !== '—')
                                    <em>{{ $fmt($c->probationarydate) }}</em>
                                @endif
                            </strong>
                        </div>
                        <div></div>
                        <div class="epf-field">
                            <span>Referred By</span>
                            <strong>{{ $r ? (trim($r->firstname . ' ' . $r->lastname) ?: $r->referredby) : '—' }}</strong>
                        </div>
                        <div class="epf-field">
                            <span>Referral Type</span>
                            <strong>
                                @if ($r && $r->referral_type)
                                    <span class="epf-tag {{ strtolower($r->referral_type) === 'med' ? 'epf-tag--teal' : 'epf-tag--amber' }}">{{ $r->referral_type }}</span>
                                @else
                                    —
                                @endif
                            </strong>
                        </div>
                        <div class="epf-field"><span>Effectivity</span><strong>{{ $fmt($r->effectivity ?? null) }}</strong></div>
                    </div>
                </section>

                {{-- Checklist --}}
                <section class="epf-card">
                    @php $ck = $d['checklist']; @endphp
                    <header class="epf-card__head">
                        <h3 class="epf-card__title">Checklist &amp; IDs</h3>
                        @if ($ck && $ck->status)
                            <span class="epf-tag {{ strtolower($ck->status) === 'cleared' ? 'epf-tag--green' : 'epf-tag--amber' }}">{{ $ck->status }}</span>
                        @endif
                    </header>
                    <div class="epf-grid">
                        <div class="epf-field"><span>Date Oriented</span><strong>{{ $fmt($ck->dateoriented ?? null) }}</strong></div>
                        <div class="epf-field"><span>Temp ID</span><strong>{{ ($ck->releasedtempid ?? '') ?: '—' }}</strong></div>
                        <div class="epf-field"><span>Permanent ID</span><strong>{{ ($ck->releasedpermanentid ?? '') ?: '—' }}</strong></div>
                    </div>
                </section>

                {{-- Emergency Contact --}}
                <section class="epf-card">
                    <h3 class="epf-card__title">Emergency Contact</h3>
                    @php $blank = fn ($v) => in_array(trim((string) $v), ['', 'N/A', '-'], true) ? '—' : $v; @endphp
                    <div class="epf-grid">
                        <div class="epf-field"><span>Contact Person</span><strong>{{ $blank($p->contactperson) }}</strong></div>
                        <div class="epf-field"><span>Relationship</span><strong>{{ $blank($p->relationship) }}</strong></div>
                        <div class="epf-field"><span>Contact Number</span><strong>{{ $blank($p->contactnumber) }}</strong></div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    {{-- ==================== POINTS MODAL ==================== --}}
    <div class="epf-modal-backdrop" id="epfPoints" hidden>
        <div class="epf-modal" role="dialog" aria-modal="true" aria-labelledby="epfPointsTitle">
            <header class="epf-modal__head">
                <div>
                    <h3 id="epfPointsTitle">Points Breakdown</h3>
                    <span class="epf-hint">{{ $d['currentYear'] }} · {{ $d['points'] }} total</span>
                </div>
                <button type="button" class="hr-icon-btn" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
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
                        $byMonth[date('F', strtotime($row->logindate))][] = [
                            'label'  => $translations[$row->offense] ?? $row->offense,
                            'points' => (float) $row->points,
                            'date'   => $row->logindate,
                        ];
                    }
                    foreach ($byMonth as &$rows) {
                        usort($rows, fn ($a, $b) => $b['points'] <=> $a['points']);
                    }
                    unset($rows);
                @endphp

                @if (empty($byMonth))
                    <div class="epf-modal__empty">
                        <i class="fa-regular fa-circle-check"></i>
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
</div>
@endsection
