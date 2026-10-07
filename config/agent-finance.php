<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    | Ingestion limits for government financial documents. Allowed extensions
    | also gate MIME validation (spec §7). Files are stored on a private disk
    | and never in the public web root (spec §8).
    */
    'documents' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_size_kb' => (int) env('DOCUMENTS_MAX_SIZE_KB', 20480),
        'allowed_extensions' => [
            'pdf', 'png', 'jpg', 'jpeg', 'webp', 'tiff', 'tif', 'docx', 'xlsx', 'csv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI layer (spec §34, §32, §33)
    |--------------------------------------------------------------------------
    | The provider is replaceable by configuration only. Implementations live
    | in app/Services/Ai and implement AIProviderInterface. Prompts are
    | versioned in the database (ai_prompts), never hardcoded.
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'default_model' => env('AI_DEFAULT_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'providers' => [
            'openai' => ['key' => env('OPENAI_API_KEY')],
            'anthropic' => ['key' => env('ANTHROPIC_API_KEY')],
            'google' => ['key' => env('GOOGLE_AI_API_KEY')],
            'local' => [
                'key' => env('LOCAL_AI_API_KEY'),
                'base_url' => env('LOCAL_AI_BASE_URL'),
            ],
        ],
        'chat' => [
            // Per-user daily guardrails (roadmap P0-2). The token budget reads
            // ai_usage rows for the current user/day; the message limit counts
            // persisted conversation turns. Both default high enough for real
            // work while acting as a cost ceiling.
            'rate_limit_per_minute' => (int) env('AI_CHAT_RATE_LIMIT_PER_MINUTE', 10),
            'daily_message_limit' => (int) env('AI_CHAT_DAILY_MESSAGE_LIMIT', 100),
            'daily_token_budget' => (int) env('AI_CHAT_DAILY_TOKEN_BUDGET', 200_000),
            // Re-ask once with a JSON-only repair instruction when the model
            // answers in prose instead of the labeled-statement contract (§47).
            'json_retry' => (bool) env('AI_CHAT_JSON_RETRY', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR (spec §9)
    |--------------------------------------------------------------------------
    */
    'ocr' => [
        'driver' => env('OCR_DRIVER', 'tesseract'),
        'language' => 'ind+eng',
    ],

    /*
    |--------------------------------------------------------------------------
    | SIHANDAL V2 (spec §16)
    |--------------------------------------------------------------------------
    | SIHANDAL is the financial system of record. Agent Finance talks to it
    | exclusively through its REST API. An empty base URL activates the fake
    | client for development/tests (offline resilience, spec §50).
    */
    'sihandal' => [
        'base_url' => env('SIHANDAL_BASE_URL'),
        'api_key' => env('SIHANDAL_API_KEY'),
        'timeout' => (int) env('SIHANDAL_TIMEOUT', 15),
        'cache_ttl' => (int) env('SIHANDAL_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation engine (spec §12, §15)
    |--------------------------------------------------------------------------
    | Extracted fields below low_confidence are flagged; below
    | review_confidence they enter the review queue.
    */
    'validation' => [
        'low_confidence' => (float) env('VALIDATION_LOW_CONFIDENCE', 0.60),
        'review_confidence' => (float) env('VALIDATION_REVIEW_CONFIDENCE', 0.85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security (spec §7, §35)
    |--------------------------------------------------------------------------
    | Malware scanning on upload. Driver is configuration only:
    | "fake" keeps dev/tests offline, "clamav" shells out to
    | clamscan when the engine is installed on the host.
    */
    'security' => [
        'malware_driver' => env('MALWARE_DRIVER', 'fake'),
    ],

];
