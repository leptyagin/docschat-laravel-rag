<x-admin.layout title="Settings">
    <x-admin.card class="max-w-2xl">
        <form method="post" action="{{ route('admin.settings.update') }}" class="flex flex-col gap-4">
            @csrf
            @method('put')

            <x-admin.field name="name" label="Bot name">
                <input id="name" name="name" type="text" required maxlength="100" value="{{ old('name', $bot->name) }}" class="admin-input">
            </x-admin.field>

            <x-admin.field name="greeting" label="Greeting">
                <textarea id="greeting" name="greeting" required maxlength="300" rows="2" class="admin-input">{{ old('greeting', $bot->greeting) }}</textarea>
            </x-admin.field>

            <x-admin.field name="color" label="Color">
                <input id="color" name="color" type="color" required value="{{ old('color', $bot->theme['color']) }}" class="h-11 w-20 cursor-pointer rounded-xl border border-line bg-night p-1">
            </x-admin.field>

            <x-admin.field name="fallback_contact" label="Fallback contact" hint="An email or URL shown when the bot cannot answer.">
                <input id="fallback_contact" name="fallback_contact" type="text" required maxlength="255" value="{{ old('fallback_contact', $bot->fallback_contact) }}" class="admin-input">
            </x-admin.field>

            <x-admin.field name="suggested_questions" label="Suggested questions" hint="Shown when the chat opens. One per line, at most four.">
                <textarea id="suggested_questions" name="suggested_questions" rows="4" class="admin-input">{{ old('suggested_questions', implode("\n", $bot->suggested_questions)) }}</textarea>
            </x-admin.field>

            <x-admin.field name="embed_origins" label="Allowed sites" hint="Sites that may embed the chat, such as https://www.example.com. One per line. Empty means any site.">
                <textarea id="embed_origins" name="embed_origins" rows="3" class="admin-input">{{ old('embed_origins', implode("\n", $bot->embed_origins)) }}</textarea>
            </x-admin.field>

            <x-admin.field name="documents_public" label="Public documents" hint="Off keeps files private: visitors get answers and citations, but cannot open or download whole documents.">
                <input type="hidden" name="documents_public" value="0">
                <label class="flex items-center gap-2 text-sm">
                    <input id="documents_public" name="documents_public" type="checkbox" value="1" @checked(old('documents_public', $bot->documents_public))>
                    Let visitors open whole documents
                </label>
            </x-admin.field>

            <x-admin.button class="self-start">Save settings</x-admin.button>
        </form>
    </x-admin.card>
</x-admin.layout>
