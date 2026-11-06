<?php

declare(strict_types=1);

use App\Models\Bot;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

it('forbids framing the pages of the application', function (string $route): void {
    get(route($route))->assertHeader('X-Frame-Options', 'DENY');
})->with(['home', 'admin.login']);

it('forbids framing a source file', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('documents/guide.md', 'Text');
    $bot = Bot::factory()->create();
    $document = Document::factory()->for($bot)->create(['file_path' => 'documents/guide.md']);

    get(route('widget.documents.show', [$bot->public_key, $document]))->assertHeader('X-Frame-Options', 'DENY');
});

it('forbids framing an error page', function (): void {
    get(route('widget.documents.show', [Bot::factory()->create()->public_key, 999]))
        ->assertNotFound()
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('leaves the widget page free to be framed', function (): void {
    get(route('widget.show', Bot::factory()->create()->public_key))->assertHeaderMissing('X-Frame-Options');
});
