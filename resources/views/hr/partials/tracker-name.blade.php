{{-- One line: Last name, First Middle Suffix (same as the HR roster). Expects: $e --}}
@php
    $na         = fn ($v) => in_array(strtoupper(trim((string) $v)), ['', 'N/A', '-'], true) ? '' : trim((string) $v);
    $givenNames = trim($na($e->firstname) . ' ' . $na($e->middlename));
    $suffix     = $na($e->suffix);
@endphp
<td class="hr-name" title="{{ trim($e->lastname . ', ' . $givenNames . ' ' . $suffix) }}">
    <strong>{{ $e->lastname }}</strong>, {{ $givenNames }}
    @if ($suffix !== '')
        <span class="hr-suffix">{{ $suffix }}</span>
    @endif
</td>
