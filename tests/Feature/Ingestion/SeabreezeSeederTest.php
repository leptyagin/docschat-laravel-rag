<?php

declare(strict_types=1);

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use Database\Seeders\SeabreezeSeeder;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\seed;

it('creates the demo bot and indexes all seven documents', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    seed(SeabreezeSeeder::class);

    $bot = Bot::query()->where('slug', 'seabreeze')->firstOrFail();

    expect($bot->public_key)->toStartWith('pk_')
        ->and($bot->documents()->where('status', DocumentStatus::Ready)->count())->toBe(7)
        ->and($bot->documents()->where('source_type', DocumentSourceType::Pdf)->count())->toBe(2)
        ->and($bot->chunks()->drafts()->count())->toBe(0);
});

it('keeps page numbers and heading trails for the PDF documents', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    seed(SeabreezeSeeder::class);

    $chunk = Chunk::query()->where('content', 'like', '%Storm Guarantee applies only%')->firstOrFail();

    expect($chunk->heading)->toBe('Cancellation Policy > Storm Guarantee')
        ->and($chunk->page)->toBeGreaterThanOrEqual(1)
        ->and(Chunk::query()->whereNotNull('page')->max('page'))->toBe(3)
        ->and(Chunk::query()->where('content', 'like', 'Old Town has 2 accessible rooms%')->firstOrFail()->heading)
        ->toBe('Accessibility Guide > Old Town > Rooms');
});

it('keeps property-specific rules apart in separate chunks', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    seed(SeabreezeSeeder::class);

    $heading = fn (string $text): ?string => Chunk::query()->where('content', 'like', "%{$text}%")->firstOrFail()->heading;

    expect($heading('Dogs and cats up to 15 kg'))->toBe('Pets Policy > Harbor View')
        ->and($heading('Dogs up to 25 kg'))->toBe('Pets Policy > Dune Retreat');
});

it('does not embed anything again when run a second time', function (): void {
    Storage::fake('local');
    $embeddings = fakeEmbeddings();

    seed(SeabreezeSeeder::class);
    $batches = count($embeddings->batches);
    $publicKey = Bot::query()->firstOrFail()->public_key;

    seed(SeabreezeSeeder::class);

    expect($embeddings->batches)->toHaveCount($batches)
        ->and(Bot::query()->firstOrFail()->public_key)->toBe($publicKey)
        ->and(Document::query()->count())->toBe(7);
});
