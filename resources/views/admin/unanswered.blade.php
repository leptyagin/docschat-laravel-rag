<x-admin.layout title="Unanswered">
    <p class="text-sm text-haze">Questions the documents could not answer, with similar wordings grouped. These show what to add next.</p>

    <x-admin.card>
        <x-admin.question-groups :groups="$groups" />
    </x-admin.card>
</x-admin.layout>
