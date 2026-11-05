@props(['groups'])

@if ($groups === [])
    <p class="text-sm text-slate-500">Nothing here yet. Every question so far had an answer in the documents.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($groups as $group)
            <li class="flex items-start justify-between gap-4 py-3">
                <div class="min-w-0">
                    <a href="{{ route('admin.conversations.show', $group->conversationId) }}" class="break-words font-medium text-brand-dark hover:underline">{{ $group->question }}</a>
                    @if ($group->variants !== [])
                        <p class="mt-1 text-sm break-words text-slate-500">Also asked as: {{ implode(' · ', array_slice($group->variants, 0, 3)) }}</p>
                    @endif
                    <p class="mt-1 text-xs text-slate-500">Last asked {{ $group->lastAskedAt->diffForHumans() }}</p>
                </div>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-sm font-medium" title="Times asked">{{ $group->count }}×</span>
            </li>
        @endforeach
    </ul>
@endif
