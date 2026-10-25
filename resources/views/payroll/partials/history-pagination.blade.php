{{-- Pagination links for the payroll history table ($paginator->links('payroll.partials.history-pagination')) --}}
@if ($paginator->hasPages())
    <nav class="history-pager" role="navigation" aria-label="History pages">

        @if ($paginator->onFirstPage())
            <span class="pager-btn is-disabled" aria-disabled="true">
                <i class="fa-solid fa-chevron-left"></i>
                <span class="pager-label">Previous</span>
            </span>
        @else
            <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                <i class="fa-solid fa-chevron-left"></i>
                <span class="pager-label">Previous</span>
            </a>
        @endif

        <ul class="pager-pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pager-gap" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="pager-page is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pager-page" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">
                <span class="pager-label">Next</span>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        @else
            <span class="pager-btn is-disabled" aria-disabled="true">
                <span class="pager-label">Next</span>
                <i class="fa-solid fa-chevron-right"></i>
            </span>
        @endif

    </nav>
@endif
