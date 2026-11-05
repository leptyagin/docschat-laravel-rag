<?php

declare(strict_types=1);

namespace App\Ai\Data;

use App\Enums\MessageRole;

final readonly class ChatMessage
{
    public function __construct(
        public MessageRole $role,
        public string $content,
    ) {}
}
