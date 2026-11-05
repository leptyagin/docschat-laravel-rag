<?php

declare(strict_types=1);

use App\Http\Middleware\BlockReadOnlyUsers;
use App\Models\Document;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\from;
use function Pest\Laravel\get;

it('blocks every change with a friendly notice', function (string $method, Closure $url): void {
    actingAsDemo();
    $bot = managedBot();
    $document = Document::factory()->for($bot)->ready()->create(['title' => 'Unchanged']);

    from(route('admin.documents.index'))
        ->call($method, $url($document), [
            'file' => UploadedFile::fake()->create('guide.md', 1),
            'url' => 'https://hotel.example',
            'name' => 'Hacked',
            'greeting' => 'Hacked',
            'color' => '#000000',
            'fallback_contact' => 'x',
        ])
        ->assertRedirect(route('admin.documents.index'))
        ->assertSessionHas('notice');

    expect(Document::query()->count())->toBe(1)
        ->and($document->refresh()->status->value)->toBe('ready')
        ->and($bot->refresh()->name)->not->toBe('Hacked');
})->with([
    'upload' => ['POST', fn (): string => route('admin.documents.store')],
    'add page' => ['POST', fn (): string => route('admin.documents.store-url')],
    're-index' => ['POST', fn (Document $document): string => route('admin.documents.reindex', $document)],
    'delete' => ['DELETE', fn (Document $document): string => route('admin.documents.destroy', $document)],
    'settings' => ['PUT', fn (): string => route('admin.settings.update')],
]);

test('every state-changing admin route is covered by the read-only guard', function (): void {
    $unguarded = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'admin'))
        ->reject(fn ($route): bool => $route->methods() === ['GET', 'HEAD'])
        ->reject(fn ($route): bool => in_array($route->uri(), ['admin/login', 'admin/logout'], true))
        ->reject(fn ($route): bool => in_array(BlockReadOnlyUsers::class, $route->gatherMiddleware(), true));

    expect($unguarded)->toBeEmpty();
});

it('lets the demo account read every page, with changes visibly switched off', function (string $route): void {
    actingAsDemo();
    Document::factory()->for(managedBot())->ready()->create();

    get(route($route))
        ->assertOk()
        ->assertSee('read-only demo');
})->with(['admin.dashboard', 'admin.documents.index', 'admin.conversations.index', 'admin.unanswered', 'admin.settings.edit']);

it('disables the buttons for the demo account only', function (): void {
    managedBot();

    actingAsDemo();
    get(route('admin.settings.edit'))->assertSee('Switched off in the read-only demo');

    actingAsAdmin();
    get(route('admin.settings.edit'))->assertDontSee('Switched off in the read-only demo');
});
