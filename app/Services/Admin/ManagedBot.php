<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Bot;
use Illuminate\Container\Attributes\Config;

/**
 * The bot this panel manages. The data model supports many, the panel shows one.
 */
final class ManagedBot
{
    private ?Bot $bot = null;

    public function __construct(
        #[Config('docschat.demo.bot_slug')] private readonly string $slug,
    ) {}

    public function get(): Bot
    {
        return $this->bot ??= Bot::query()->where('slug', $this->slug)->firstOrFail();
    }
}
