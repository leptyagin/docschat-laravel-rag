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
            'timeout' => (int) env('DOCSCHAT_CHAT_TIMEOUT', 30),
        ],

        'embeddings' => [
            'provider' => env('DOCSCHAT_EMBEDDING_PROVIDER', 'openai'),
            'model' => env('DOCSCHAT_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('DOCSCHAT_EMBEDDING_DIMENSIONS', 1536),
            'batch_size' => (int) env('DOCSCHAT_EMBEDDING_BATCH_SIZE', 100),
            'timeout' => (int) env('DOCSCHAT_EMBEDDING_TIMEOUT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Ingestion
    |--------------------------------------------------------------------------
    */

    'ingestion' => [
        'disk' => env('DOCSCHAT_DOCUMENTS_DISK', 'local'),
        'pdf_timeout' => (int) env('DOCSCHAT_PDF_TIMEOUT', 60),

        'http' => [
            'user_agent' => env('DOCSCHAT_CRAWLER_USER_AGENT', 'DocsChatBot/1.0'),
            'connect_timeout' => (int) env('DOCSCHAT_CRAWLER_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('DOCSCHAT_CRAWLER_TIMEOUT', 20),
            'max_bytes' => (int) env('DOCSCHAT_CRAWLER_MAX_BYTES', 5 * 1024 * 1024),
        ],
    ],

    'chunking' => [
        'target_tokens' => (int) env('DOCSCHAT_CHUNK_TARGET_TOKENS', 600),
        'overlap_tokens' => (int) env('DOCSCHAT_CHUNK_OVERLAP_TOKENS', 80),
    ],

];
