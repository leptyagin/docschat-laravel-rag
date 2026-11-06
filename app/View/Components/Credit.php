<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The line that names the author of the project, with links that open in a new
 * tab. A link without a value is not shown.
 */
final class Credit extends Component
{
    public function __construct(
        #[Config('docschat.demo.credit.name')] public readonly ?string $name,
        #[Config('docschat.demo.credit.github_url')] public readonly ?string $githubUrl,
        #[Config('docschat.demo.credit.upwork_url')] public readonly ?string $upworkUrl,
    ) {}

    public function shouldRender(): bool
    {
        return filled($this->name);
    }

    public function render(): View
    {
        return view('components.credit');
    }
}
