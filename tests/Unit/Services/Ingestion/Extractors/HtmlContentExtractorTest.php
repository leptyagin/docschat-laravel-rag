<?php

declare(strict_types=1);

use App\Services\Ingestion\Extractors\HtmlContentExtractor;

function articleExtraction(): string
{
    $html = (string) file_get_contents(__DIR__.'/../../../../Fixtures/article.html');

    return new HtmlContentExtractor()->extract($html)->pages[0]->text;
}

it('keeps the main content with headings, lists and table rows', function (): void {
    expect(articleExtraction())->toBe(implode("\n\n", [
        '# Parking',
        'Guests park in the underground garage.',
        '## Fees',
        '- Cars: 25 dollars per night',
        '- Motorcycles: 10 dollars per night',
        'Vehicle | Height limit',
        'Van | 2.1 m',
        'Loose text inside a div.',
    ]));
});

it('strips navigation, footers, scripts and other page furniture', function (string $noise): void {
    expect(articleExtraction())->not->toContain($noise);
})->with([
    'navigation' => 'Rooms menu link',
    'site header' => 'Site logo',
    'footer' => 'Copyright footer text',
    'sidebar' => 'Book now',
    'form' => 'Newsletter',
    'script' => 'secret-tracking-code',
]);

it('uses the normalised page title', function (): void {
    $html = (string) file_get_contents(__DIR__.'/../../../../Fixtures/article.html');

    expect(new HtmlContentExtractor()->extract($html)->title)->toBe('Parking at the Marina Hotel');
});

it('falls back to the body when a page has no main element', function (): void {
    $extraction = new HtmlContentExtractor()->extract(
        '<html><body><header>Logo</header><h2>Spa</h2><p>Open daily.</p><footer>Legal</footer></body></html>',
    );

    expect($extraction->pages[0]->text)->toBe("## Spa\n\nOpen daily.");
});

it('reports a page without text as empty', function (): void {
    expect(new HtmlContentExtractor()->extract('<html><body><script>1</script></body></html>')->isEmpty())->toBeTrue();
});
