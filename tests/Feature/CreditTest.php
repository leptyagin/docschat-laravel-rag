<?php

declare(strict_types=1);

use App\Models\Bot;
use App\Models\Document;
use Illuminate\Support\Facades\Blade;

use function Pest\Laravel\get;

const GITHUB_LINK = '<a class="credit__link" href="https://git.example/author" target="_blank" rel="noopener noreferrer">GitHub</a>';
const UPWORK_LINK = 'href="https://hire.example/author" target="_blank" rel="noopener noreferrer">Hire me on Upwork</a>';

beforeEach(function (): void {
    config()->set('docschat.demo.credit', [
        'name' => 'Sergei L.',
        'github_url' => 'https://git.example/author',
        'upwork_url' => 'https://hire.example/author',
    ]);
});

it('shows the author in the header and the footer of the public pages', function (Closure $url): void {
    $bot = managedBot();
    $document = Document::factory()->for($bot)->ready()->create();

    $response = get($url($bot, $document))->assertOk();

    expect(substr_count($response->getContent(), GITHUB_LINK))->toBe(2)
        ->and(substr_count($response->getContent(), UPWORK_LINK))->toBe(2);
})->with([
    'landing page' => [fn (Bot $bot, Document $document): string => route('home')],
    'reader' => [fn (Bot $bot, Document $document): string => route('reader.show', [$bot->public_key, $document])],
]);

it('shows the author on the admin login page', function (): void {
    $response = get(route('admin.login'))->assertOk();

    expect(substr_count($response->getContent(), UPWORK_LINK))->toBe(2);
});

it('shows the author in the sidebar and the footer of the admin panel', function (): void {
    managedBot();
    actingAsAdmin();

    $response = get(route('admin.dashboard'))->assertOk();

    expect(substr_count($response->getContent(), GITHUB_LINK))->toBe(2)
        ->and(substr_count($response->getContent(), UPWORK_LINK))->toBe(2);
});

it('leaves the embedded widget to the site that hosts it', function (): void {
    get(route('widget.show', managedBot()->public_key))->assertOk()->assertDontSee('Hire me on Upwork');
});

it('hides a link without a value', function (): void {
    config()->set('docschat.demo.credit.upwork_url', '');

    $credit = Blade::render('<x-credit />');

    expect($credit)->toContain('GitHub')
        ->and(str_contains($credit, 'Hire me on Upwork'))->toBeFalse();
});

it('renders nothing without a name', function (): void {
    config()->set('docschat.demo.credit.name', null);

    expect(trim(Blade::render('<x-credit />')))->toBeEmpty();
});
