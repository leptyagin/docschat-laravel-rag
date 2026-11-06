<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

function registerProvider(): void
{
    new AppServiceProvider(app())->register();
}

it('refuses to boot when a configured model has no price', function (string $setting): void {
    config()->set('docschat.ai.driver', 'laravel-ai');
    config()->set($setting, 'unlisted-model');

    expect(registerProvider(...))->toThrow(InvalidArgumentException::class, 'unlisted-model');
})->with([
    'answers' => 'docschat.ai.chat.model',
    'rewrites' => 'docschat.ai.chat.rewrite_model',
    'embeddings' => 'docschat.ai.embeddings.model',
]);

it('refuses to boot on a price that would only fail after the model is paid for', function (mixed $price): void {
    config()->set('docschat.ai.driver', 'laravel-ai');
    config()->set('docschat.prices.broken-model', $price);

    expect(registerProvider(...))->toThrow(InvalidArgumentException::class, 'broken-model');
})->with([
    'a number instead of a string' => [['input' => 0.1, 'output' => '0.5']],
    'text that is not a number' => [['input' => '0.1', 'output' => 'free']],
    'a missing side' => [['input' => '0.1']],
    'not a price at all' => ['0.1'],
]);

it('boots with the default models', function (): void {
    config()->set('docschat.ai.driver', 'laravel-ai');

    expect(registerProvider(...))->not->toThrow(InvalidArgumentException::class);
});

it('needs no prices for the fake driver, which spends nothing', function (): void {
    config()->set('docschat.ai.chat.model', 'unlisted-model');

    expect(registerProvider(...))->not->toThrow(InvalidArgumentException::class);
});

describe('settings from the environment', function (): void {
    /**
     * @return array<string, mixed>
     */
    function docschatConfigWith(string $variable, string $value): array
    {
        $_ENV[$variable] = $value;

        try {
            return require config_path('docschat.php');
        } finally {
            unset($_ENV[$variable]);
        }
    }

    it('adds model prices to the built-in table', function (): void {
        $config = docschatConfigWith('DOCSCHAT_MODEL_PRICES', '{"vendor/model":{"input":"0.15","output":"0.25"}}');

        expect($config['prices']['vendor/model'])->toBe(['input' => '0.15', 'output' => '0.25'])
            ->and($config['prices'])->toHaveKey('gpt-6-luna');
    });

    it('reads the extra request body', function (): void {
        $config = docschatConfigWith('DOCSCHAT_CHAT_EXTRA_BODY', '{"chat_template_kwargs":{"enable_thinking":false}}');

        expect($config['ai']['chat']['provider_options']['openai-compatible'])
            ->toBe(['chat_template_kwargs' => ['enable_thinking' => false]]);
    });

    it('treats an empty value as nothing configured', function (): void {
        $config = docschatConfigWith('DOCSCHAT_CHAT_EXTRA_BODY', '');

        expect($config['ai']['chat']['provider_options']['openai-compatible'])->toBe([]);
    });

    it('stops on broken JSON rather than running without the setting', function (): void {
        expect(fn (): array => docschatConfigWith('DOCSCHAT_MODEL_PRICES', '{not json'))->toThrow(JsonException::class);
    });
});
