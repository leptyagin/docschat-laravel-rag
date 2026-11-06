@if ($paginator->hasPages())
    <nav aria-label="Pagination" class="flex flex-wrap items-center justify-center gap-1.5 text-sm">
        @php($pageClasses = 'grid h-9 min-w-9 place-items-center rounded-xl border border-line bg-panel px-3 font-medium text-mist transition-colors hover:border-brand')

        @if ($paginator->previousPageUrl())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pageClasses }}">Previous</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-haze">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page" class="grid h-9 min-w-9 place-items-center rounded-xl bg-brand px-3 font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $pageClasses }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->nextPageUrl())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pageClasses }}">Next</a>
        @endif
    </nav>
@endif
