<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Contracts\ModerationModel;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * The SDK has no moderation API, so this calls the endpoint directly. An outage
 * must not take the chat down with it, so a failed check lets the text through.
 */
final readonly class OpenAiModerationModel implements ModerationModel
{
    public function __construct(
        private HttpFactory $http,
        #[Config('ai.providers.openai.url')] private string $url,
        #[Config('ai.providers.openai.key')] private ?string $key,
        #[Config('docschat.moderation.model')] private string $model,
        #[Config('docschat.moderation.timeout')] private int $timeout,
    ) {}

    public function isFlagged(string $text): bool
    {
        try {
            return $this->http
                ->withToken((string) $this->key)
                ->acceptJson()
                ->connectTimeout($this->timeout)
                ->timeout($this->timeout)
                ->post(rtrim($this->url, '/').'/moderations', ['model' => $this->model, 'input' => $text])
                ->throw()
                ->json('results.0.flagged') === true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
