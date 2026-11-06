<?php

declare(strict_types=1);

use App\Models\Chunk;
use App\Models\Document;

use function Pest\Laravel\get;

it('protects the pages that print document and visitor text', function (string $route): void {
    managedBot();

    $policy = (string) get(route($route))->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'none'")
        ->toContain("img-src 'self' data:")
        ->toContain("frame-ancestors 'none'")
        ->toMatch("/script-src 'self' 'nonce-\w+'/");

    expect(str_contains($policy, 'unsafe-eval'))->toBeFalse();
})->with(['home', 'admin.login']);

it('protects the reader, and lets its inline script run', function (): void {
    $bot = managedBot();
    $document = Document::factory()->for($bot)->ready()->create();
    Chunk::factory()->published()->for($document)->for($bot)->create();

    $response = get(route('reader.show', [$bot->public_key, $document]));

    preg_match("/'nonce-(\w+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

    $response->assertOk()->assertSee('<script nonce="'.$matches[1].'">', escape: false);
});

it('protects the admin panel', function (): void {
    actingAsAdmin();
    managedBot();

    expect(get(route('admin.dashboard'))->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

it('makes a new nonce for every response', function (): void {
    managedBot();

    $policy = fn (): string => (string) get(route('home'))->headers->get('Content-Security-Policy');

    expect($policy())->not->toBe($policy());
});
