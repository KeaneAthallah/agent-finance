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

];
