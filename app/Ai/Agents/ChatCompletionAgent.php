<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Data\ChatRequest;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Adapts a provider-neutral ChatRequest to the SDK's agent contract.
 */
final class ChatCompletionAgent implements Agent, HasProviderOptions
{
    use Promptable;

    /**
     * @param  array<string, array<string, mixed>>  $providerOptions  Request options keyed by provider.
     */
    public function __construct(
        private readonly ChatRequest $request,
        private readonly array $providerOptions = [],
    ) {}

    public function instructions(): string
    {
        return $this->request->systemPrompt;
    }

    public function maxTokens(): int
    {
        return $this->request->maxTokens;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $this->providerOptions[$provider instanceof Lab ? $provider->value : $provider] ?? [];
    }
}
