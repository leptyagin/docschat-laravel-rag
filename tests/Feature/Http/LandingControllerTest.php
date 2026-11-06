<?php

declare(strict_types=1);

use App\Models\Document;

use function Pest\Laravel\get;

it('presents the demo with the widget open and the example questions', function (): void {
    $bot = managedBot();

    get(route('home'))
        ->assertOk()
        ->assertSee('Demo project. Seabreeze Hotels is a fictional company.')
        ->assertSee('Can I bring my dog?')
        ->assertSee('How it works')
        ->assertSee('data-bot="'.$bot->public_key.'"', escape: false)
        ->assertSee('data-open="true"', escape: false);
});

it('warns visitors that their questions are visible in the public admin panel', function (): void {
    get(route('home'))->assertSee('appear in the public read-only admin panel');
});

it('shows only the links that are configured', function (): void {
    managedBot();
    config()->set('docschat.demo.links', ['repository_url' => 'https://git.example/repo', 'author_url' => null, 'author_label' => 'Work together']);

    get(route('home'))
        ->assertSee('https://git.example/repo')
        ->assertDontSee('Work together')
        ->assertSee('Read-only admin panel');
});

it('opens the documents of the demo from the library', function (): void {
    $bot = managedBot();
    $document = Document::factory()->for($bot)->ready()->create(['title' => 'Pets Policy']);
    Document::factory()->for($bot)->create(['title' => 'Still Processing']);

    get(route('home'))
        ->assertSee('Pets Policy')
        ->assertSee(route('reader.show', [$bot->public_key, $document]))
        ->assertDontSee('Still Processing');
});

it('still renders before the demo bot has been seeded', function (): void {
    get(route('home'))->assertOk()->assertDontSee('data-bot', escape: false);
});
