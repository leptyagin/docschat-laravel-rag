<?php

declare(strict_types=1);

namespace App\Services\Answer;

use Illuminate\Filesystem\Filesystem;

/**
 * Prompts live in resources/prompts, so they can be tuned without touching code.
 */
final readonly class PromptLibrary
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<string, string>  $variables  Replaces each {{name}} placeholder.
     */
    public function render(string $name, array $variables = []): string
    {
        $placeholders = [];

        foreach ($variables as $variable => $value) {
            $placeholders['{{'.$variable.'}}'] = $value;
        }

        return trim(strtr($this->files->get(resource_path("prompts/{$name}.md")), $placeholders));
    }
}
