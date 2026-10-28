{{-- One audit_log entry (from BenefitsController::historyEntries). Expects: $entry, optional $showEmployee --}}
@php
    $labels = \App\Services\EmployeeBenefitsService::FIELD_LABELS;
    $showEmployee = $showEmployee ?? true;
@endphp
<article class="bf-entry">
    <header class="bf-entry-head">
        <div>
            @if ($showEmployee)
                <a class="bf-entry-emp" href="{{ route('payroll.benefits.history', ['search' => $entry->idno]) }}">
                    {{ $entry->employee ?: 'Unknown employee' }} <span class="bf-id">{{ $entry->idno }}</span>
                </a>
            @endif
            <div class="bf-entry-meta">
                <i class="fa-regular fa-user"></i> {{ $entry->user ?: '—' }}
                <span aria-hidden="true">·</span> <i class="fa-regular fa-clock"></i> {{ $entry->when ?: '—' }}
                <span aria-hidden="true">·</span> <span class="badge">{{ $entry->source }}</span>
            </div>
        </div>
        @if ($entry->old || $entry->new)
            <button type="button" class="bf-btn bf-btn-ghost bf-btn-sm" data-toggle-snapshot aria-expanded="false">
                <i class="fa-solid fa-table-list"></i> Full record
            </button>
        @endif
    </header>

    @if ($entry->changes->isEmpty())
        <p class="bf-muted bf-small">Saved without changing salary, benefits or bank details.</p>
    @else
        <ul class="bf-changes">
            @foreach ($entry->changes as $c)
                <li>
                    <span class="bf-change-field">{{ $c['field'] ? ($labels[$c['field']] ?? $c['field']) : 'Change' }}</span>
                    @if ($c['field'] === 'account_number' || $c['field'] === null)
                        <span class="bf-change-new">{{ ucfirst($c['new']) }}</span>
                    @else
                        <span class="bf-change-old">{{ $c['old'] !== '' ? $c['old'] : 'blank' }}</span>
                        <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
                        <span class="bf-change-new">{{ $c['new'] !== '' ? $c['new'] : 'blank' }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($entry->old || $entry->new)
        <div class="bf-snapshot" hidden>
            <table class="bank-table">
                <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                <tbody>
                    @foreach (array_unique(array_merge(array_keys($entry->old), array_keys($entry->new))) as $field)
                        @php
                            $before = $entry->old[$field] ?? null;
                            $after = $entry->new[$field] ?? null;
                        @endphp
                        <tr class="{{ (string) $before !== (string) $after ? 'bf-diff' : '' }}">
                            <td>{{ $labels[$field] ?? $field }}</td>
                            <td>{{ $before ?? '—' }}</td>
                            <td>{{ $after ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</article>
