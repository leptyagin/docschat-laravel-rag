@props(['groups'])

@if ($groups === [])
    <p class="text-sm text-haze">Nothing here yet. Every question so far had an answer in the documents.</p>
@else
    <ul class="divide-y divide-line">
        @foreach ($groups as $group)
            <li class="flex items-start justify-between gap-4 py-3">
                <div class="min-w-0">
                    <a href="{{ route('admin.conversations.show', $group->conversationId) }}" class="break-words font-medium text-brand-light hover:underline">{{ $group->question }}</a>
                    @if ($group->variants !== [])
                        <p class="mt-1 text-sm break-words text-haze">Also asked as: {{ implode(' · ', array_slice($group->variants, 0, 3)) }}</p>
                    @endif
                    <p class="mt-1 text-xs text-haze">Last asked {{ $group->lastAskedAt->diffForHumans() }}</p>
                </div>
                <span class="shrink-0 rounded-full border border-line bg-raised px-2.5 py-0.5 text-sm font-medium" title="Times asked">{{ $group->count }}×</span>
            </li>
        @endforeach
    </ul>
@endif
