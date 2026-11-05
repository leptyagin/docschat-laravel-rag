<x-admin.layout title="Settings">
    <x-admin.card class="max-w-2xl">
        <form method="post" action="{{ route('admin.settings.update') }}" class="flex flex-col gap-4">
            @csrf
            @method('put')

            <x-admin.field name="name" label="Bot name">
                <input id="name" name="name" type="text" required maxlength="100" value="{{ old('name', $bot->name) }}" class="rounded-md border border-slate-300 px-3 py-2">
            </x-admin.field>

            <x-admin.field name="greeting" label="Greeting">
                <textarea id="greeting" name="greeting" required maxlength="300" rows="2" class="rounded-md border border-slate-300 px-3 py-2">{{ old('greeting', $bot->greeting) }}</textarea>
            </x-admin.field>

            <x-admin.field name="color" label="Color">
                <input id="color" name="color" type="color" required value="{{ old('color', $bot->theme['color']) }}" class="h-10 w-20 rounded-md border border-slate-300 p-1">
            </x-admin.field>

            <x-admin.field name="fallback_contact" label="Fallback contact" hint="An email or URL shown when the bot cannot answer.">
                <input id="fallback_contact" name="fallback_contact" type="text" required maxlength="255" value="{{ old('fallback_contact', $bot->fallback_contact) }}" class="rounded-md border border-slate-300 px-3 py-2">
            </x-admin.field>

            <x-admin.field name="suggested_questions" label="Suggested questions" hint="Shown when the chat opens. One per line, at most four.">
                <textarea id="suggested_questions" name="suggested_questions" rows="4" class="rounded-md border border-slate-300 px-3 py-2">{{ old('suggested_questions', implode("\n", $bot->suggested_questions)) }}</textarea>
            </x-admin.field>

            <x-admin.button class="self-start">Save settings</x-admin.button>
        </form>
    </x-admin.card>
</x-admin.layout>
