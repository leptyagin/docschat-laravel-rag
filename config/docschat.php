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
            'timeout' => (int) env('DOCSCHAT_CHAT_TIMEOUT', 30),

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
        'candidates' => (int) env('DOCSCHAT_RETRIEVAL_CANDIDATES', 20),
        'keep' => (int) env('DOCSCHAT_RETRIEVAL_KEEP', 6),
        'rrf_k' => (int) env('DOCSCHAT_RETRIEVAL_RRF_K', 60),
        'min_similarity' => (float) env('DOCSCHAT_RETRIEVAL_MIN_SIMILARITY', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Answers
    |--------------------------------------------------------------------------
    */

    'answers' => [
        'max_question_length' => (int) env('DOCSCHAT_MAX_QUESTION_LENGTH', 500),
        'max_answer_tokens' => (int) env('DOCSCHAT_MAX_ANSWER_TOKENS', 400),
        'max_rewrite_tokens' => (int) env('DOCSCHAT_MAX_REWRITE_TOKENS', 100),
        'history_messages' => (int) env('DOCSCHAT_HISTORY_MESSAGES', 6),
    ],

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
