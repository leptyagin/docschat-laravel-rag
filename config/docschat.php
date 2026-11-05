<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Language Models
    |--------------------------------------------------------------------------
    |
    | The "fake" driver answers locally and deterministically, without a
    | provider or an API key. The test suite always runs on it.
    |
    */

    'ai' => [
        'driver' => env('DOCSCHAT_AI_DRIVER', 'laravel-ai'),

        'chat' => [
            'provider' => env('DOCSCHAT_CHAT_PROVIDER', 'openai'),
            'model' => env('DOCSCHAT_CHAT_MODEL', 'gpt-6-luna'),
            'rewrite_model' => env('DOCSCHAT_REWRITE_MODEL', 'gpt-6-luna'),
            'max_rewrite_tokens' => 100,
            'timeout' => 30,

            /*
            | Answers come from the retrieved context, so reasoning would only
            | add cost and latency, and could use up the answer token limit.
            */
            'provider_options' => [
                'openai' => ['reasoning' => ['effort' => 'none']],
                'anthropic' => ['thinking' => ['type' => 'disabled']],
            ],
        ],

        'embeddings' => [
            'provider' => env('DOCSCHAT_EMBEDDING_PROVIDER', 'openai'),
            'model' => env('DOCSCHAT_EMBEDDING_MODEL', 'text-embedding-3-small'),

            /*
            | The vector columns are created with this size, so changing it
            | takes a migration as well.
            */
            'dimensions' => 1536,
            'batch_size' => 100,
            'timeout' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Ingestion
    |--------------------------------------------------------------------------
    */

    'ingestion' => [
        'disk' => env('DOCSCHAT_DOCUMENTS_DISK', 'local'),
        'max_file_bytes' => 25 * 1024 * 1024,
        'max_pdf_pages' => 1000,
        'pdf_timeout' => 60,

        'http' => [
            'user_agent' => 'DocsChatBot/1.0',
            'connect_timeout' => 5,
            'timeout' => 20,
            'max_bytes' => 5 * 1024 * 1024,
        ],
    ],

    'chunking' => [
        'target_tokens' => 600,
        'overlap_tokens' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    |
    | A question whose best cosine similarity is below "min_similarity" is
    | refused without calling the chat model.
    |
    */

    'retrieval' => [
        'candidates' => 20,
        'keep' => 6,
        'rrf_k' => 60,
        'min_similarity' => (float) env('DOCSCHAT_RETRIEVAL_MIN_SIMILARITY', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Answers
    |--------------------------------------------------------------------------
    */

    'answers' => [
        'max_question_length' => 500,
        'max_answer_tokens' => (int) env('DOCSCHAT_MAX_ANSWER_TOKENS', 400),
        'history_messages' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel And Public Demo
    |--------------------------------------------------------------------------
    |
    | An account without a password cannot log in. The demo account is read-only
    | and its credentials are shown on the login page, so never reuse a real one.
    | Links without a value are not shown on the landing page.
    |
    */

    'admin' => [
        'accounts' => [
            'admin' => ['email' => env('ADMIN_EMAIL'), 'password' => env('ADMIN_PASSWORD')],
            'demo' => ['email' => env('DEMO_EMAIL'), 'password' => env('DEMO_PASSWORD')],
        ],
    ],

    'demo' => [
        'bot_slug' => 'seabreeze',
        'bot_key' => env('DEMO_BOT_KEY', 'pk_seabreeze_demo'),

        'links' => [
            'repository_url' => env('DEMO_REPOSITORY_URL'),
            'author_url' => env('DEMO_AUTHOR_URL'),
            'author_label' => env('DEMO_AUTHOR_LABEL', 'Work with me'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Protection
    |--------------------------------------------------------------------------
    |
    | The public demo runs on the owner's API key. Above the daily budget the bot
    | stops calling models and says so. Per-visitor limits are in messages.
    |
    */

    'daily_budget_usd' => (float) env('DAILY_BUDGET_USD', 2.0),

    'rate_limits' => [
        'per_hour' => 20,
        'per_day' => 60,
    ],

    /*
    | Comma-separated proxy addresses, or "*" behind a load balancer. Without
    | this, every visitor behind a proxy looks like the same IP address.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Model Prices
    |--------------------------------------------------------------------------
    |
    | US dollars per one million tokens, as strings to stay exact. A model that
    | is missing here is logged and counted as free, so keep this list current.
    |
    */

    'prices' => [
        'gpt-6-luna' => ['input' => '0.10', 'output' => '0.50'],
        'claude-haiku-5-5' => ['input' => '0.10', 'output' => '0.50'],
        'text-embedding-3-small' => ['input' => '0.02', 'output' => '0'],
        'fake-chat' => ['input' => '0', 'output' => '0'],
        'fake-embedding' => ['input' => '0', 'output' => '0'],
    ],

];
