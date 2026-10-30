@extends('layouts.app')

@section('title', 'Addons & Deductions - Accounting Portal')
@section('page-title', 'Addons & Deductions')

@section('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/benefits.css', 'resources/css/payroll-items.css'])
@endsection

@section('content')
@php
    $flash = session('items_flash');
    $icons = ['addons' => 'fa-circle-plus', 'deductions' => 'fa-circle-minus'];
@endphp

<div class="dash bf pi">

    <div class="section-head">
        <div>
            <h2>Addons &amp; Deductions</h2>
            <div class="sub">The names payroll can pick from when adding addons and deductions. Amounts are set per employee in payroll edit.</div>
        </div>
    </div>

    <div class="bf-grid pi-grid">
        @foreach ($lists as $type => $items)
            @php
                $t = $types[$type];
                $bag = $errors->getBag($type);
            @endphp

            <section class="bf-card pi-card" aria-labelledby="pi-{{ $type }}-title">
                <div class="bf-card-head">
                    <i class="fa-solid {{ $icons[$type] }}"></i>
                    <h3 id="pi-{{ $type }}-title">{{ ucfirst($type) }}</h3>
                    <span class="bf-count">{{ $items->count() }}</span>
                </div>

                <div class="bf-card-body">
                    @if ($flash && $flash['type'] === $type)
                        <div class="bf-alert {{ $flash['status'] === 'success' ? 'bf-alert-ok' : 'bf-alert-error' }}" role="{{ $flash['status'] === 'success' ? 'status' : 'alert' }}">
                            <i class="fa-solid {{ $flash['status'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>
                            {{ $flash['message'] }}
                        </div>
                    @endif

                    @if ($bag->any())
                        <div class="bf-alert bf-alert-error" role="alert">
                            <i class="fa-solid fa-circle-exclamation"></i> {{ $bag->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('payroll.items.store', $type) }}" class="pi-add">
                        @csrf
                        <input type="hidden" name="_form" value="add-{{ $type }}">
                        <input type="text" name="name" maxlength="100" required
                               placeholder="New {{ strtolower($t['label']) }} name"
                               aria-label="New {{ strtolower($t['label']) }} name"
                               value="{{ old('_form') === 'add-' . $type ? old('name') : '' }}">
                        <button type="submit" class="bf-btn bf-btn-primary">
                            <i class="fa-solid fa-plus"></i> Add
                        </button>
                    </form>

                    <ul class="pi-list">
                        @forelse ($items as $item)
                            @php
                                $inUse = $item->employees > 0 || $item->period_rows > 0;
                                $usage = collect([
                                    $item->employees ? number_format($item->employees) . ' ' . ($item->employees === 1 ? 'employee' : 'employees') : null,
                                    $item->period_rows ? number_format($item->period_rows) . ' payroll ' . ($item->period_rows === 1 ? 'entry' : 'entries') : null,
                                ])->filter()->implode(' · ');
                            @endphp
                            <li class="pi-item">
                                <div class="pi-name">
                                    <strong>{{ $item->name !== null && trim($item->name) !== '' ? $item->name : '(blank)' }}</strong>
                                    <span class="bf-muted bf-small">{{ $usage ?: 'Not used yet' }}</span>
                                </div>

                                <div class="pi-actions">
                                    @if ($inUse)
                                        <span class="bf-icon-btn is-disabled" title="In use, can't be renamed or deleted" aria-label="In use">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                    @else
                                        <details class="pi-rename">
                                            <summary class="bf-icon-btn" title="Rename" aria-label="Rename {{ $item->name }}">
                                                <i class="fa-solid fa-pen"></i>
                                            </summary>
                                            <form method="POST" action="{{ route('payroll.items.update', [$type, $item->id]) }}" class="pi-add">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" maxlength="100" required value="{{ $item->name }}" aria-label="New name">
                                                <button type="submit" class="bf-btn bf-btn-sm bf-btn-primary">Save</button>
                                            </form>
                                        </details>

                                        <form method="POST" action="{{ route('payroll.items.destroy', [$type, $item->id]) }}"
                                              onsubmit="return confirm('Delete &quot;{{ addslashes((string) $item->name) }}&quot;?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bf-icon-btn pi-delete" title="Delete" aria-label="Delete {{ $item->name }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="bf-empty-row">No {{ $type }} yet.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        @endforeach
    </div>

</div>
@endsection
