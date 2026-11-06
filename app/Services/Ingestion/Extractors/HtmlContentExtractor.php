<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Extractors;

use App\Data\Ingestion\ExtractedDocument;
use App\Data\Ingestion\ExtractedPage;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use ValueError;

/**
 * Reduces a page to its main content as Markdown-flavoured text.
 */
final class HtmlContentExtractor
{
    private const array MAIN_CONTENT_SELECTORS = ['main', '[role="main"]'];

    private const string NOISE_SELECTOR = 'script, style, noscript, template, svg, iframe, nav, footer, aside, form,'
        .' [hidden], [aria-hidden="true"], [role="navigation"], [role="contentinfo"], [role="banner"],'
        .' [style*="display:none" i], [style*="display: none" i], [style*="visibility:hidden" i], [style*="visibility: hidden" i]';

    private const array HEADINGS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];

    private const array TEXT_BLOCKS = ['p', 'pre', 'blockquote', 'dt', 'dd', 'figcaption', 'caption'];

    private const array CONTAINERS = [
        'address', 'article', 'center', 'details', 'div', 'dl', 'figure', 'header', 'main', 'ol', 'section',
        'summary', 'table', 'tbody', 'tfoot', 'thead', 'ul',
    ];

    /**
     * @param  string|null  $charset  The encoding declared by the server, which outranks one declared in the page.
     */
    public function extract(string $html, ?string $charset = null): ExtractedDocument
    {
        $document = $this->parse($html, $charset);
        $root = $this->mainContent($document);
        $title = $this->normalize($document->title);

        if ($root === null) {
            return new ExtractedDocument([], $title === '' ? null : $title);
        }

        foreach ($root->querySelectorAll(self::NOISE_SELECTOR) as $noise) {
            $noise->remove();
        }

        return new ExtractedDocument(
            pages: [new ExtractedPage($this->render($root))],
            title: $title === '' ? null : $title,
        );
    }

    private function parse(string $html, ?string $charset): HTMLDocument
    {
        try {
            return HTMLDocument::createFromString($html, LIBXML_NOERROR, $charset);
        } catch (ValueError) {
            return HTMLDocument::createFromString($html, LIBXML_NOERROR);
        }
    }

    /**
     * A single article is the content of the page. Several are a listing, where
     * each one matters, so that case falls through to the whole body.
     */
    private function mainContent(HTMLDocument $document): ?Element
    {
        foreach (self::MAIN_CONTENT_SELECTORS as $selector) {
            $element = $document->querySelector($selector);

            if ($element !== null) {
                return $element;
            }
        }

        $articles = $document->querySelectorAll('article');

        if ($articles->length === 1) {
            return $articles->item(0);
        }

        if ($document->body === null) {
            return null;
        }

        foreach ($document->body->querySelectorAll('header') as $header) {
            $header->remove();
        }

        return $document->body;
    }

    private function render(Element $root): string
    {
        $blocks = [];
        $inline = '';

        $this->walk($root, $blocks, $inline);
        $this->flushInline($blocks, $inline);

        return implode("\n\n", $blocks);
    }

    /**
     * @param  list<string>  $blocks
     */
    private function walk(Node $parent, array &$blocks, string &$inline): void
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof Text) {
                $inline .= $node->data;

                continue;
            }

            if (! $node instanceof Element) {
                continue;
            }

            $tag = strtolower($node->localName);

            if (! $this->isBlock($tag)) {
                $inline .= $tag === 'br' ? ' ' : $node->textContent;

                continue;
            }

            $this->flushInline($blocks, $inline);

            if (in_array($tag, self::CONTAINERS, true)) {
                $this->walk($node, $blocks, $inline);
                $this->flushInline($blocks, $inline);

                continue;
            }

            $block = $this->renderBlock($tag, $node);

            if ($block !== '') {
                $blocks[] = $block;
            }
        }
    }

    private function isBlock(string $tag): bool
    {
        return isset(self::HEADINGS[$tag])
            || $tag === 'li'
            || $tag === 'tr'
            || in_array($tag, self::TEXT_BLOCKS, true)
            || in_array($tag, self::CONTAINERS, true);
    }

    private function renderBlock(string $tag, Element $element): string
    {
        $text = $tag === 'tr' ? $this->renderRow($element) : $this->normalize((string) $element->textContent);

        if ($text === '') {
            return '';
        }

        return match (true) {
            isset(self::HEADINGS[$tag]) => str_repeat('#', self::HEADINGS[$tag]).' '.$text,
            $tag === 'li' => '- '.$text,
            default => $text,
        };
    }

    private function renderRow(Element $row): string
    {
        $cells = [];

        foreach ($row->children as $cell) {
            $cells[] = $this->normalize((string) $cell->textContent);
        }

        return implode(' | ', array_filter($cells, fn (string $cell): bool => $cell !== ''));
    }

    /**
     * @param  list<string>  $blocks
     */
    private function flushInline(array &$blocks, string &$inline): void
    {
        $text = $this->normalize($inline);
        $inline = '';

        if ($text !== '') {
            $blocks[] = $text;
        }
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
