<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\put;

/**
 * @return array<string, string>
 */
function settingsPayload(): array
{
    return [
        'name' => 'Marina Hotels',
        'greeting' => 'Welcome aboard!',
        'color' => '#123456',
        'fallback_contact' => 'help@marina.example',
        'suggested_questions' => "First?\n\n  Second?  \n",
    ];
}

it('shows the current settings', function (): void {
    actingAsAdmin();
    managedBot();

    get(route('admin.settings.edit'))->assertOk()->assertSee('Can I bring my dog?');
});

it('saves the settings and keeps the rest of the theme', function (): void {
    actingAsAdmin();
    $bot = managedBot();

    put(route('admin.settings.update'), settingsPayload())->assertSessionHas('status');

    $bot->refresh();

    expect($bot->name)->toBe('Marina Hotels')
        ->and($bot->greeting)->toBe('Welcome aboard!')
        ->and($bot->fallback_contact)->toBe('help@marina.example')
        ->and($bot->theme)->toBe(['color' => '#123456', 'position' => 'right'])
        ->and($bot->suggested_questions)->toBe(['First?', 'Second?']);
});

it('rejects invalid settings', function (string $field, string $value): void {
    actingAsAdmin();
    managedBot();

    put(route('admin.settings.update'), [...settingsPayload(), $field => $value])->assertSessionHasErrors($field);
})->with([
    'empty name' => ['name', ''],
    'color that is not hex' => ['color', 'red'],
    'empty contact' => ['fallback_contact', ''],
    'too many questions' => ['suggested_questions', "1\n2\n3\n4\n5"],
]);

it('saves the allowed sites in the form a browser reports them', function (): void {
    actingAsAdmin();
    $bot = managedBot();

    put(route('admin.settings.update'), [
        ...settingsPayload(),
        'embed_origins' => "https://Hotel.example/\n\n  http://localhost:9000  ",
    ])->assertSessionHasNoErrors();

    expect($bot->refresh()->embed_origins)->toBe(['https://hotel.example', 'http://localhost:9000']);
});

it('refuses a site that could change the security policy', function (string $origin): void {
    actingAsAdmin();
    $bot = managedBot();

    put(route('admin.settings.update'), [...settingsPayload(), 'embed_origins' => $origin])->assertSessionHasErrors('embed_origins.0');

    expect($bot->refresh()->embed_origins)->toBe([]);
})->with([
    'another directive' => ['https://a.example; script-src *'],
    'a wildcard' => ['*'],
    'a path' => ['https://a.example/page'],
    'another scheme' => ['javascript:alert(1)'],
    'a space' => ['https://a.example https://b.example'],
]);

it('switches public documents on and off', function (string $value, bool $expected): void {
    actingAsAdmin();
    $bot = managedBot();

    put(route('admin.settings.update'), [...settingsPayload(), 'documents_public' => $value])->assertSessionHasNoErrors();

    expect($bot->refresh()->documents_public)->toBe($expected);
})->with([
    'off' => ['0', false],
    'on' => ['1', true],
]);
