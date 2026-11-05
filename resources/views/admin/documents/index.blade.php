@php
    $isIndexing = $documents->contains(fn ($document) => $document->status->isInFlight());
@endphp

<x-admin.layout title="Documents" :refresh-seconds="$isIndexing ? 5 : null">
    <div class="grid gap-4 md:grid-cols-2">
        <x-admin.card>
            <h2 class="mb-3 font-semibold">Upload a file</h2>
            <form method="post" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                @csrf
                <x-admin.field name="file" label="PDF, Markdown or text file">
                    <input id="file" name="file" type="file" required accept=".pdf,.md,.txt" class="max-w-full text-sm">
                </x-admin.field>
                <x-admin.field name="title" label="Title (optional)">
                    <input id="title" name="title" type="text" maxlength="255" value="{{ old('title') }}" class="rounded-md border border-slate-300 px-3 py-2">
                </x-admin.field>
                <x-admin.button class="self-start">Upload</x-admin.button>
            </form>
        </x-admin.card>

        <x-admin.card>
            <h2 class="mb-3 font-semibold">Add a web page</h2>
            <form method="post" action="{{ route('admin.documents.store-url') }}" class="flex flex-col gap-3">
                @csrf
                <x-admin.field name="url" label="Page URL" hint="The page must be public and allow crawling in its robots.txt.">
                    <input id="url" name="url" type="url" required maxlength="2048" placeholder="https://" value="{{ old('url') }}" class="rounded-md border border-slate-300 px-3 py-2">
                </x-admin.field>
                <x-admin.button class="self-start">Add page</x-admin.button>
            </form>
        </x-admin.card>
    </div>

    <x-admin.card flush>
        <ul class="divide-y divide-slate-100">
            @forelse ($documents as $document)
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-medium break-words">{{ $document->title }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                            <x-admin.status-badge :status="$document->status" />
                            <span class="uppercase">{{ $document->source_type->value }}</span>
                            <span>{{ trans_choice(':count chunk|:count chunks', $document->chunk_count) }}</span>
                            <span>{{ $document->updated_at->diffForHumans() }}</span>
                        </p>
                        @if ($document->error)
                            <p class="mt-1 text-sm break-words text-red-700">{{ $document->error }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <form method="post" action="{{ route('admin.documents.reindex', $document) }}">
                            @csrf
                            <x-admin.button variant="secondary">Re-index</x-admin.button>
                        </form>
                        <form method="post" action="{{ route('admin.documents.destroy', $document) }}">
                            @csrf
                            @method('delete')
                            <x-admin.button variant="danger" aria-label="Delete {{ $document->title }}">Delete</x-admin.button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="p-4 text-sm text-slate-500">No documents yet. Upload a file or add a web page to get started.</li>
            @endforelse
        </ul>
    </x-admin.card>

    {{ $documents->links() }}
</x-admin.layout>
