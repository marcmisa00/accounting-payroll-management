@extends('layouts.app')

@section('title', 'Resigned Employee - Accounting Portal')
@section('page-title', 'Resigned Employee')

@section('styles')
    @vite(['resources/css/hr-roster.css', 'resources/js/hr-roster.js'])
@endsection

@section('content')
@php
    // HRIS stores empty dates as NULL, 0000-00-00 or 0001-01-01
    $fmt = fn ($date, $format = 'M d, Y') => $date && !in_array($date, ['0000-00-00', '0001-01-01'], true)
        ? date($format, strtotime($date)) : '—';
    $blank = fn ($v) => in_array(strtoupper(trim((string) $v)), ['', 'N/A'], true) ? '—' : $v;

    $resignedOn = $resignation->dateresigned ?? null;

    $categoryClass = function (?string $cat) {
        $cat = strtolower((string) $cat);
        return match (true) {
            str_contains($cat, 'minor') => 'hr-badge-warning',
            str_contains($cat, 'major'), str_contains($cat, 'grave') => 'hr-badge-danger',
            default => 'hr-badge-neutral',
        };
    };
@endphp

<div class="epf">

    {{-- ==================== HEADER ==================== --}}
    <header class="epf-hero">
        <div class="epf-hero__bar">
            <nav class="epf-crumbs" aria-label="Breadcrumb">
                <a href="{{ $listUrl }}" class="hr-icon-btn" aria-label="Back to Resigned Employees">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <a href="{{ $listUrl }}" class="epf-crumbs__link">Resigned Employees</a>
                <span class="epf-crumbs__sep" aria-hidden="true">/</span>
                <span aria-current="page">Profile</span>
            </nav>
        </div>

        <div class="epf-hero__identity">
            <div class="epf-hero__avatar" aria-hidden="true">{{ $initials }}</div>

            <div class="epf-hero__who">
                <div class="epf-hero__name-row">
                    <h1 class="epf-hero__name">{{ $fullName }}</h1>
                    <span class="hr-badge hr-badge-danger"><i class="fa-solid fa-circle-xmark"></i> Resigned</span>
                </div>
                <p class="epf-hero__role">
                    {{ $det->jobtitle ?: 'No job title' }}
                    @if ($det->department)
                        <span aria-hidden="true">·</span> {{ $det->department }}
                    @endif
                </p>
                <div class="epf-hero__meta">
                    <span class="epf-hero__idno">ID {{ $idno }}</span>
                    @if ($companyname)
                        <span><i class="fa-regular fa-building"></i> {{ $companyname }}</span>
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

        <dl class="epf-stats">
            <div class="epf-stat">
                <dt>Date hired</dt>
                <dd>{{ $fmt($det->dateofhired) }}</dd>
            </div>
            <div class="epf-stat">
                <dt>Regularization</dt>
                <dd>{{ $fmt($det->dateofregular) }}</dd>
            </div>
            <div class="epf-stat epf-stat--danger">
                <dt>Date resigned</dt>
                <dd>{{ $fmt($resignedOn) }}</dd>
            </div>
            <div class="epf-stat epf-stat--danger">
                <dt>Length of service</dt>
                <dd>{{ $service ?? '—' }}</dd>
            </div>
        </dl>
    </header>

    <div class="epf-body">

        {{-- ============ SIDEBAR ============ --}}
        <aside class="epf-side">
            <section class="epf-card">
                <h3 class="epf-card__title">Personal</h3>
                <dl class="epf-kv">
                    <div><dt>Nickname</dt><dd>{{ $blank($p->nickname) }}</dd></div>
                    <div><dt>Birthdate</dt><dd>{{ $fmt($p->birthdate) }}</dd></div>
                    <div><dt>Gender</dt><dd>{{ match ($p->sex) { 'M' => 'Male', 'F' => 'Female', default => '—' } }}</dd></div>
                    <div><dt>Civil status</dt><dd>{{ match ($p->civilstatus) { 'S' => 'Single', 'M' => 'Married', 'W' => 'Widowed', default => $p->civilstatus ?: '—' } }}</dd></div>
                    <div><dt>Eligibility</dt><dd>{{ $blank($p->eligibility) }}</dd></div>
                </dl>
            </section>

            <section class="epf-card">
                <h3 class="epf-card__title">Contact</h3>
                <dl class="epf-kv epf-kv--stacked">
                    <div><dt>Company email</dt><dd class="epf-break">{{ $blank($p->comp_email) }}</dd></div>
                    <div><dt>Phone</dt><dd>{{ $blank($p->phone_number) }}</dd></div>
                    <div><dt>Address</dt><dd>{{ $blank($p->address) }}</dd></div>
                    <div>
                        <dt>Emergency contact</dt>
                        <dd>
                            {{ $blank($p->contactperson) }}
                            @if (trim((string) $p->contactnumber) !== '')
                                <span class="epf-kv__sub">{{ $p->contactnumber }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
        </aside>

        {{-- ============ MAIN ============ --}}
        <main class="epf-main">

            <section class="epf-card">
                <h3 class="epf-card__title">Employment Details</h3>
                <div class="epf-grid epf-grid--3">
                    <div class="epf-field"><span>Employee ID</span><strong>{{ $idno }}</strong></div>
                    <div class="epf-field"><span>Position</span><strong>{{ $det->jobtitle ?: '—' }}</strong></div>
                    <div class="epf-field"><span>Department</span><strong>{{ $det->department ?: '—' }}</strong></div>
                    <div class="epf-field"><span>Company</span><strong>{{ $companyname ?: '—' }}</strong></div>
                    <div class="epf-field"><span>Status</span><strong><span class="hr-badge hr-badge-danger">{{ $det->status }}</span></strong></div>
                    <div class="epf-field"><span>Work Shift</span><strong>{{ $shift ?? '—' }}</strong></div>
                    <div class="epf-field"><span>Location</span><strong>{{ $det->work_area ?: '—' }}</strong></div>
                    <div class="epf-field"><span>Setup</span><strong>{{ $det->location ?: '—' }}</strong></div>
                    <div class="epf-field"><span>Reason for Leaving</span><strong>{{ $blank($resignation->reason ?? null) }}</strong></div>
                </div>
            </section>

            {{-- Infractions, newest year first --}}
            <section class="epf-card">
                <header class="epf-card__head">
                    <h3 class="epf-card__title">Infractions</h3>
                    @if ($infractionCount)
                        <span class="epf-hint">{{ $infractionCount }} {{ Str::plural('record', $infractionCount) }}</span>
                    @endif
                </header>

                @forelse ($infractionsByYear as $year => $rows)
                    <div class="epf-year">
                        <div class="epf-year__head">
                            <strong>{{ $year }}</strong>
                            <span class="hr-count">{{ $rows->count() }} {{ Str::plural('record', $rows->count()) }}</span>
                        </div>
                        <div class="hr-table-wrap epf-year__table">
                            <table class="hr-table hr-table--compact">
                                <thead>
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Date Issued</th>
                                        <th>Date Served</th>
                                        <th>Date of Incident</th>
                                        <th>Type of Memo</th>
                                        <th>Type of Offense</th>
                                        <th class="text-center">Points</th>
                                        <th>Memo No.</th>
                                        <th class="text-center">Category</th>
                                        <th>Date of Suspension</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $r)
                                        <tr>
                                            <td class="text-center hr-muted">{{ $loop->iteration }}</td>
                                            <td class="hr-date">{{ $fmt($r->dateissued) }}</td>
                                            <td class="hr-date">{{ $fmt($r->dateserved) }}</td>
                                            {{-- Free text in HRIS, shown as stored --}}
                                            <td>{{ $blank($r->dateofincident) }}</td>
                                            <td>{{ $blank($r->typeofmemo) }}</td>
                                            <td class="hr-wrap">{{ $blank($r->typeofoffense) }}</td>
                                            <td class="text-center">{{ is_numeric($r->points) && (float) $r->points != 0 ? number_format((float) $r->points, 1) : '—' }}</td>
                                            <td class="hr-id">{{ $blank($r->memonumber) }}</td>
                                            <td class="text-center">
                                                @if (trim((string) $r->typecat) !== '')
                                                    <span class="hr-badge {{ $categoryClass($r->typecat) }}">{{ $r->typecat }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="hr-wrap">{{ $blank($r->dateofsuspension) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="epf-none"><i class="fa-regular fa-circle-check"></i> No infractions recorded for this employee.</p>
                @endforelse
            </section>

            {{-- Attendance points, newest year first --}}
            <section class="epf-card">
                <h3 class="epf-card__title">Attendance Points</h3>

                @forelse ($pointsByYear as $year => $rows)
                    <div class="epf-year">
                        <div class="epf-year__head">
                            <strong>{{ $year }}</strong>
                            <span class="hr-count">{{ number_format($rows->sum(fn ($r) => (float) $r->points), 1) }} points</span>
                        </div>
                        <div class="hr-table-wrap epf-year__table">
                            <table class="hr-table hr-table--compact">
                                <thead>
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Date</th>
                                        <th class="text-center">Points</th>
                                        <th>Offense</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $r)
                                        <tr>
                                            <td class="text-center hr-muted">{{ $loop->iteration }}</td>
                                            <td class="hr-date">{{ $fmt($r->logindate) }}</td>
                                            <td class="text-center">{{ number_format((float) $r->points, 1) }}</td>
                                            <td>{{ $r->label }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="epf-none"><i class="fa-regular fa-circle-check"></i> No attendance points recorded.</p>
                @endforelse
            </section>
        </main>
    </div>
</div>
@endsection
