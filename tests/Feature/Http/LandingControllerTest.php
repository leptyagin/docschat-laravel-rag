<?php

declare(strict_types=1);

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

it('shows only the links that are configured', function (): void {
    managedBot();
    config()->set('docschat.demo.links', ['repository_url' => 'https://git.example/repo', 'author_url' => null, 'author_label' => 'Hire me']);

    get(route('home'))
        ->assertSee('https://git.example/repo')
        ->assertDontSee('Hire me')
        ->assertSee('Read-only admin panel');
});

it('still renders before the demo bot has been seeded', function (): void {
    get(route('home'))->assertOk()->assertDontSee('data-bot', escape: false);
});
