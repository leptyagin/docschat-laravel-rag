<x-admin.layout title="Conversations">
    <x-admin.card flush>
        <ul class="divide-y divide-slate-100">
            @forelse ($conversations as $conversation)
                <li>
                    <a href="{{ route('admin.conversations.show', $conversation) }}" class="block p-4 hover:bg-slate-50">
                        <p class="font-medium break-words">{{ $conversation->first_question ?? 'No messages yet' }}</p>
                        <p class="mt-1 flex flex-wrap gap-x-3 text-sm text-slate-500">
                            <span>{{ $conversation->created_at->diffForHumans() }}</span>
                            <span>{{ trans_choice(':count message|:count messages', $conversation->messages_count) }}</span>
                            <span>${{ number_format((float) $conversation->messages_sum_cost_usd, 5) }}</span>
                        </p>
                    </a>
                </li>
            @empty
                <li class="p-4 text-sm text-slate-500">No conversations yet.</li>
            @endforelse
        </ul>
    </x-admin.card>

    {{ $conversations->links() }}
</x-admin.layout>
