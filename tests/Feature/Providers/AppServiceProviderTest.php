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

it('boots with the default models', function (): void {
    config()->set('docschat.ai.driver', 'laravel-ai');

    expect(registerProvider(...))->not->toThrow(InvalidArgumentException::class);
});

it('needs no prices for the fake driver, which spends nothing', function (): void {
    config()->set('docschat.ai.chat.model', 'unlisted-model');

    expect(registerProvider(...))->not->toThrow(InvalidArgumentException::class);
});
