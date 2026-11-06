<?php

declare(strict_types=1);

namespace App\Services\Reader;

use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Document text is untrusted: a file may contain anything. Raw HTML is dropped
 * and links with a script scheme are removed, so the result is safe to print.
 */
final readonly class PassageRenderer
{
    private GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    public function toHtml(string $text): string
    {
        return $this->converter->convert($text)->getContent();
    }
}
