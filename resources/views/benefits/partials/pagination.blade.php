{{-- Pagination for the Salary & Benefits pages ($paginator->links('benefits.partials.pagination')) --}}
@if ($paginator->hasPages())
    <nav class="bf-pager" role="navigation" aria-label="Pages">
        @if ($paginator->onFirstPage())
            <span class="bf-pager-btn is-disabled" aria-disabled="true"><i class="fa-solid fa-chevron-left"></i></span>
        @else
            <a class="bf-pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="bf-pager-gap" aria-hidden="true">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="bf-pager-btn is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="bf-pager-btn" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="bf-pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
        @else
            <span class="bf-pager-btn is-disabled" aria-disabled="true"><i class="fa-solid fa-chevron-right"></i></span>
        @endif
    </nav>
@endif
