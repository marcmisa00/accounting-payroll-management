@extends('layouts.app')

@section('title', 'Salary & Benefits - Accounting Portal')
@section('page-title', 'Salary & Benefits')

@section('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/benefits.css', 'resources/js/benefits.js'])
@endsection

@section('content')
@php
    use App\Services\EmployeeBenefitsService as Benefits;

    $labels = Benefits::FIELD_LABELS;
    // Saved value as the form shows it (2 decimals for amounts)
    $amount = fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2, '.', '');
    $saved = fn (string $f) => $amount($pay->$f ?? null);
    $savedType = $pay->salary_type ?? 'Rated';
    $savedDate = ($pay->effective_date ?? null) && !in_array($pay->effective_date, ['0000-00-00', '0001-01-01'], true)
        ? date('Y-m-d', strtotime($pay->effective_date)) : '';
    $fmtDate = fn ($d) => $d ? date('M d, Y', strtotime($d)) : '—';
    $suffix = in_array(strtoupper(trim((string) $emp->suffix)), ['', 'N/A'], true) ? '' : ' ' . trim($emp->suffix);
    $coKey = 'co-' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $emp->company));
    $nav = fn ($idno) => route('payroll.benefits.edit', ['idno' => $idno] + array_filter(['search' => $context['search'] ?? null]));

    $groups = [
        'Government Contributions' => ['icon' => 'fa-landmark', 'fields' => ['sss', 'phic', 'hdmf']],
        'Other Deductions'         => ['icon' => 'fa-shield-heart', 'fields' => ['philcare', 'generali', 'fwd', 'tax', 'otherbenefits']],
    ];
    $accountAction = old('account_action', 'keep');
@endphp

<div class="dash bf">

    {{-- ===================== Header ===================== --}}
    <div class="bf-crumbs">
        <a href="{{ $listUrl }}" class="bf-icon-btn" aria-label="Back to Salary & Benefits"><i class="fa-solid fa-arrow-left"></i></a>
        <a href="{{ $listUrl }}">Salary &amp; Benefits</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $emp->lastname }}, {{ $emp->firstname }}</span>

        @if ($position)
            <div class="bf-pager-nav" id="bfPager">
                @if ($prev)
                    <a href="{{ $nav($prev['idno']) }}" class="bf-icon-btn" data-nav title="Previous: {{ $prev['name'] }}" aria-label="Previous employee: {{ $prev['name'] }}"><i class="fa-solid fa-chevron-left"></i></a>
                @else
                    <span class="bf-icon-btn is-disabled" aria-disabled="true"><i class="fa-solid fa-chevron-left"></i></span>
                @endif
                <span class="bf-muted"><strong>{{ $position['at'] }}</strong> of {{ $position['of'] }} in {{ $emp->department_name ?: 'department' }}</span>
                @if ($next)
                    <a href="{{ $nav($next['idno']) }}" class="bf-icon-btn" data-nav title="Next: {{ $next['name'] }}" aria-label="Next employee: {{ $next['name'] }}"><i class="fa-solid fa-chevron-right"></i></a>
                @else
                    <span class="bf-icon-btn is-disabled" aria-disabled="true"><i class="fa-solid fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    <div class="company bf-hero {{ $coKey }}">
        <div class="company-head">
            <div>
                <h2>{{ $emp->lastname }}, {{ $emp->firstname }}{{ $suffix }}</h2>
                <div class="meta">
                    <span class="bf-id">ID {{ $emp->idno }}</span> ·
                    {{ $emp->jobtitle ?: 'No job title' }} · {{ $emp->department_name ?: 'No department' }} · {{ $emp->companyname ?: $emp->company }}
                </div>
            </div>
            <div class="total">
                <span>Salary rate</span>
                ₱{{ number_format((float) ($pay->salary ?? 0), 2) }}
            </div>
        </div>
        <div class="bf-hero-meta">
            <span><i class="fa-solid fa-tag"></i> {{ Benefits::SALARY_TYPES[$savedType] ?? $savedType }}</span>
            <span><i class="fa-regular fa-calendar"></i> Effective {{ $fmtDate($savedDate ?: null) }}</span>
            <span><i class="fa-solid fa-building-columns"></i> {{ $bankName ?: 'No bank' }}{{ $account ? ' · ' . $account : '' }}</span>
            @if ($pay && ($pay->updatedby || $pay->addedby))
                <span class="bf-muted"><i class="fa-regular fa-pen-to-square"></i>
                    Last saved by {{ $pay->updatedby ?: $pay->addedby }}
                    {{ ($pay->updateddatetime ?: $pay->addeddatetime) ? 'on ' . date('M d, Y h:i A', strtotime($pay->updateddatetime ?: $pay->addeddatetime)) : '' }}
                </span>
            @elseif (!$pay)
                <span class="bf-warn-text"><i class="fa-solid fa-circle-info"></i> No payroll record yet; saving creates one.</span>
            @endif
        </div>
    </div>

    {{-- ===================== Messages ===================== --}}
    @if (session('benefits_saved'))
        <div class="bf-alert bf-alert-ok" role="status"><i class="fa-solid fa-circle-check"></i> Saved. The change is in the history below.</div>
    @elseif (session('benefits_unchanged'))
        <div class="bf-alert" role="status"><i class="fa-solid fa-circle-info"></i> Nothing changed, so nothing was saved.</div>
    @endif
    @if (session('benefits_error'))
        <div class="bf-alert bf-alert-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> {{ session('benefits_error') }}</div>
    @endif
    @if ($errors->any())
        <div class="bf-alert bf-alert-error" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i> Nothing was saved. Please check the highlighted fields.
        </div>
    @endif

    {{-- ===================== Form ===================== --}}
    <form method="POST" action="{{ route('payroll.benefits.update', ['idno' => $emp->idno] + array_filter(['search' => $context['search'] ?? null])) }}"
          id="bfForm" class="bf-form" novalidate>
        @csrf

        <div class="bf-grid">
            {{-- Salary --}}
            <section class="company bf-card bf-card-salary">
                <header class="bf-card-head"><i class="fa-solid fa-peso-sign"></i> Salary</header>
                <div class="bf-card-body">
                    <div class="bf-field @error('salary') has-error @enderror">
                        <label for="salary">Salary rate</label>
                        <div class="bf-money">
                            <span>₱</span>
                            <input type="text" inputmode="decimal" id="salary" name="salary" value="{{ old('salary', $saved('salary')) }}"
                                   data-original="{{ $saved('salary') }}" data-label="{{ $labels['salary'] }}" data-money required>
                        </div>
                        @error('salary') <p class="bf-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="bf-field">
                        <span class="bf-label">Salary type</span>
                        <div class="bf-segment" role="radiogroup" aria-label="Salary type" data-original="{{ $savedType }}" data-label="{{ $labels['salary_type'] }}">
                            @foreach (Benefits::SALARY_TYPES as $value => $label)
                                <label>
                                    <input type="radio" name="salary_type" value="{{ $value }}" @checked(old('salary_type', $savedType) === $value)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('salary_type') <p class="bf-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="bf-field @error('effective_date') has-error @enderror">
                        <label for="effective_date">Effective date</label>
                        <input type="date" id="effective_date" name="effective_date" value="{{ old('effective_date', $savedDate) }}"
                               data-original="{{ $savedDate }}" data-label="{{ $labels['effective_date'] }}">
                        @error('effective_date') <p class="bf-error">{{ $message }}</p> @enderror
                        <p class="bf-hint" id="bfDateHint" hidden><i class="fa-solid fa-circle-info"></i> The salary rate changed: set the date the new rate starts.</p>
                    </div>

                    <div class="bf-readonly">
                        <span>Previous salary</span>
                        <strong>{{ ($pay->previous_salary ?? null) ? '₱' . number_format((float) $pay->previous_salary, 2) : '—' }}</strong>
                        <small>Set automatically to the old rate when the rate changes. Payroll uses it for days before the effective date.</small>
                    </div>
                </div>
            </section>

            {{-- Contributions / deductions --}}
            @foreach ($groups as $title => $group)
                <section class="company bf-card">
                    <header class="bf-card-head"><i class="fa-solid {{ $group['icon'] }}"></i> {{ $title }}</header>
                    <div class="bf-card-body bf-fields-2">
                        @foreach ($group['fields'] as $f)
                            <div class="bf-field @error($f) has-error @enderror">
                                <label for="{{ $f }}">{{ $labels[$f] }}</label>
                                <div class="bf-money">
                                    <span>₱</span>
                                    <input type="text" inputmode="decimal" id="{{ $f }}" name="{{ $f }}" value="{{ old($f, $saved($f)) }}"
                                           placeholder="0.00" data-original="{{ $saved($f) }}" data-label="{{ $labels[$f] }}" data-money>
                                </div>
                                @error($f) <p class="bf-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- Bank --}}
            <section class="company bf-card">
                <header class="bf-card-head"><i class="fa-solid fa-building-columns"></i> Bank</header>
                <div class="bf-card-body">
                    <div class="bf-field @error('bank') has-error @enderror">
                        <label for="bank">Bank</label>
                        <div class="select-wrap">
                            <select id="bank" name="bank" class="pick" data-original="{{ in_array($bankName, Benefits::BANKS, true) ? $bankName : '' }}" data-label="{{ $labels['bank'] }}">
                                <option value="">— Select bank —</option>
                                @foreach (Benefits::BANKS as $b)
                                    <option value="{{ $b }}" @selected(old('bank', $bankName) === $b)>{{ $b }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('bank') <p class="bf-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="bf-field @error('account_number') has-error @enderror" id="bfAccount">
                        <span class="bf-label">Account number</span>
                        <input type="hidden" name="account_action" value="{{ $accountAction }}" data-account-action>

                        <div class="bf-account" data-account-view @if ($accountAction === 'change') hidden @endif>
                            <span class="bf-account-mask {{ $accountAction === 'remove' ? 'is-removed' : '' }}" data-account-mask>
                                {{ $account ?: 'No account number on file' }}
                            </span>
                            <button type="button" class="bf-btn bf-btn-ghost bf-btn-sm" data-account="change">
                                <i class="fa-solid fa-pen"></i> {{ $account ? 'Change' : 'Add' }}
                            </button>
                            @if ($account)
                                <button type="button" class="bf-btn bf-btn-ghost bf-btn-sm bf-btn-danger" data-account="remove" @if ($accountAction === 'remove') hidden @endif>
                                    <i class="fa-solid fa-trash"></i> Remove
                                </button>
                                <button type="button" class="bf-btn bf-btn-ghost bf-btn-sm" data-account="keep" @unless ($accountAction === 'remove') hidden @endunless>
                                    <i class="fa-solid fa-rotate-left"></i> Undo remove
                                </button>
                            @endif
                        </div>

                        <div class="bf-account-edit" data-account-edit @unless ($accountAction === 'change') hidden @endunless>
                            {{-- Never pre-filled: the saved number is not sent to the browser --}}
                            <input type="text" inputmode="numeric" name="account_number" autocomplete="off" placeholder="New account number"
                                   @disabled($accountAction !== 'change') aria-label="New account number">
                            <button type="button" class="bf-btn bf-btn-ghost bf-btn-sm" data-account="keep"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                        @error('account_number') <p class="bf-error">{{ $message }}</p> @enderror
                        <p class="bf-hint">Stored encrypted. Only the last 4 digits are ever shown.</p>
                    </div>
                </div>
            </section>
        </div>

        <div class="bf-actions">
            <a href="{{ $listUrl }}" class="bf-btn bf-btn-ghost">Cancel</a>
            <button type="submit" class="bf-btn bf-btn-primary" id="bfSave"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
        </div>
    </form>

    {{-- ===================== Recent changes ===================== --}}
    <div class="section-head bf-history-head">
        <div>
            <h2>Recent changes</h2>
            <div class="sub">Latest saves for this employee from the legacy portal and this app.</div>
        </div>
        <a href="{{ route('payroll.benefits.history', ['search' => $emp->idno]) }}" class="bf-btn bf-btn-ghost">All history</a>
    </div>
    @forelse ($history as $entry)
        @include('benefits.partials.entry', ['entry' => $entry, 'showEmployee' => false])
    @empty
        <div class="empty"><i class="fa-regular fa-folder-open"></i><div>No changes recorded yet.</div></div>
    @endforelse

    {{-- Confirmation with the list of changes (filled by benefits.js) --}}
    <div class="bf-modal-backdrop" id="bfConfirm" hidden>
        <div class="bf-modal" role="dialog" aria-modal="true" aria-labelledby="bfConfirmTitle">
            <h3 id="bfConfirmTitle">Save these changes?</h3>
            <p class="bf-muted bf-small">{{ $emp->lastname }}, {{ $emp->firstname }} · ID {{ $emp->idno }}</p>
            <ul class="bf-changes" data-confirm-list></ul>
            <p class="bf-confirm-note"><i class="fa-solid fa-circle-info"></i> Payroll periods that are already posted are not changed. A pending payroll uses the new values the next time it is opened in Edit Payroll.</p>
            <div class="bf-actions">
                <button type="button" class="bf-btn bf-btn-ghost" data-confirm-cancel>Go back</button>
                <button type="button" class="bf-btn bf-btn-primary" data-confirm-ok><i class="fa-solid fa-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>
@endsection
