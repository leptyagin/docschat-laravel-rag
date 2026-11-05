<x-admin.layout title="Conversation">
    <a href="{{ route('admin.conversations.index') }}" class="text-sm text-brand-dark hover:underline">← All conversations</a>

    <p class="-mt-4 text-sm text-slate-500">Started {{ $conversation->created_at->toDayDateTimeString() }}</p>

    <ol class="flex flex-col gap-4">
        @foreach ($messages as $message)
            @if ($message->role === \App\Enums\MessageRole::User)
                <li class="max-w-2xl self-end rounded-lg bg-brand/10 p-3">
                    <p class="break-words whitespace-pre-line">{{ $message->content }}</p>
                    @if ($message->rewritten_query && $message->rewritten_query !== $message->content)
                        <p class="mt-1 text-xs text-slate-500">Searched for: {{ $message->rewritten_query }}</p>
                    @endif
                </li>
            @else
                <li class="w-full max-w-2xl">
                    <x-admin.card>
                        <p class="break-words whitespace-pre-line">{{ $message->content }}</p>

                        @if ($message->sources)
                            <h2 class="mt-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Sources</h2>
                            <ul class="mt-1 flex flex-col gap-2 text-sm">
                                @foreach ($message->sources as $source)
                                    <li class="rounded-md bg-slate-50 p-2">
                                        <p class="font-medium">[{{ $source['number'] }}] {{ $source['title'] }}@if ($source['page']), page {{ $source['page'] }}@endif</p>
                                        <p class="mt-1 break-words text-slate-600">{{ $source['excerpt'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <p class="mt-3 flex flex-wrap gap-x-3 text-xs text-slate-500">
                            @if ($message->refusal_reason)
                                <span class="font-medium text-amber-800">{{ str_replace('_', ' ', ucfirst($message->refusal_reason->value)) }}</span>
                            @endif
                            @if ($message->model)
                                <span>{{ $message->model }}</span>
                            @endif
                            <span>{{ number_format((int) $message->prompt_tokens) }} in / {{ number_format((int) $message->completion_tokens) }} out</span>
                            <span>${{ number_format((float) $message->cost_usd, 6) }}</span>
                            <span>{{ number_format((int) $message->latency_ms / 1000, 1) }} s</span>
                            @if ($message->feedback)
                                <span>Rated {{ $message->feedback->value }}</span>
                            @endif
                        </p>
                    </x-admin.card>
                </li>
            @endif
        @endforeach
    </ol>
</x-admin.layout>
