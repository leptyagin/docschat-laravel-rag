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
