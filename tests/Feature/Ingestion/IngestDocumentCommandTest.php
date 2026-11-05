<?php

declare(strict_types=1);

use App\Enums\DocumentSourceType;
use App\Jobs\Ingestion\ExtractText;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

it('queues a file for the bot given by slug', function (): void {
    Storage::fake('local');
    Queue::fake([ExtractText::class]);
    $bot = Bot::factory()->create(['slug' => 'marina']);

    artisan('docschat:ingest', ['source' => markdownFile('Text.'), '--bot' => 'marina', '--title' => 'Guide'])
        ->expectsOutputToContain('[Guide] is pending')
        ->assertSuccessful();

    $document = Document::query()->firstOrFail();

    expect($document->bot_id)->toBe($bot->id)
        ->and($document->source_type)->toBe(DocumentSourceType::Text);

    Queue::assertPushed(fn (ExtractText $job): bool => $job->document->is($document));
});

it('queues a URL', function (): void {
    Queue::fake([ExtractText::class]);
    Bot::factory()->create(['slug' => 'seabreeze']);

    artisan('docschat:ingest', ['source' => 'https://hotel.example/faq#top'])->assertSuccessful();

    $document = Document::query()->firstOrFail();

    expect($document->source_type)->toBe(DocumentSourceType::Url)
        ->and($document->source_url)->toBe('https://hotel.example/faq');
});

it('fails with a clear message for an unknown bot', function (): void {
    artisan('docschat:ingest', ['source' => 'https://hotel.example', '--bot' => 'ghost'])
        ->expectsOutputToContain('No bot with the slug [ghost]')
        ->assertFailed();
});

it('fails with a clear message for an unreadable file', function (): void {
    Bot::factory()->create(['slug' => 'seabreeze']);

    artisan('docschat:ingest', ['source' => '/nonexistent/guide.md'])
        ->expectsOutputToContain('cannot be read')
        ->assertFailed();
});
