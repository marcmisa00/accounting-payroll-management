{{-- Pagination links for the HR roster ($paginator->links('hr.partials.pagination')) --}}
@if ($paginator->hasPages())
    <nav class="hr-pager" role="navigation" aria-label="Roster pages">

        @if ($paginator->onFirstPage())
            <span class="hr-pager-btn is-disabled" aria-disabled="true"><i class="fa-solid fa-chevron-left"></i></span>
        @else
            <a class="hr-pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="hr-pager-gap" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="hr-pager-btn is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="hr-pager-btn" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="hr-pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
        @else
            <span class="hr-pager-btn is-disabled" aria-disabled="true"><i class="fa-solid fa-chevron-right"></i></span>
        @endif

    </nav>
@endif
