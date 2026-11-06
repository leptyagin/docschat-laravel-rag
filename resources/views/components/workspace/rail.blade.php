@props(['bot', 'library', 'current' => null])

<nav id="ws-rail-content" class="ws-rail__content" aria-label="Documents">
    <p class="ws-rail__title">Library</p>

    <ul class="ws-rail__list">
        @foreach ($library as $item)
            @php($isCurrent = $current?->id === $item->id)
            <li>
                <a
                    class="ws-rail__document"
                    href="{{ route('reader.show', [$bot->public_key, $item->id]) }}"
                    data-soft
                    @if ($isCurrent) aria-current="page" @endif
                >
                    {{ $item->title }}
                </a>

                @if ($isCurrent && $current->outline() !== [])
                    <ul class="ws-rail__outline">
                        @foreach ($current->outline() as $section)
                            <li>
                                <a href="{{ route('reader.show', [$bot->public_key, $item->id]) }}#{{ $section->anchor }}" data-soft data-section-link>{{ $section->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
