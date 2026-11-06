<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Providers
    |--------------------------------------------------------------------------
    |
    | DocsChat talks to these through its own ChatModel and EmbeddingModel
    | interfaces. Anthropic has no embeddings API, so embeddings stay on OpenAI.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),
    'default_for_embeddings' => 'openai',

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'redis'),
            'individually' => true,
        ],
    ],

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', false),
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
            'url' => env('OPENAI_COMPATIBLE_URL'),

            // Without this a streamed answer reports no token usage, and the budget would have to guess.
            'stream_options' => ['include_usage' => true],
        ],
    ],

];
