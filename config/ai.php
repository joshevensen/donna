<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Donna thinks with Anthropic and embeds with Voyage AI. Which model does
    | which job lives in config/donna.php; this file only wires providers.
    |
    */

    'default' => 'anthropic',
    'default_for_embeddings' => 'voyageai',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Embeddings are stored on the rows they describe, so there is nothing to
    | gain from caching them a second time.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
            'individually' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | The Anthropic beta header keeps the SDK's default (web fetch) and adds
    | server-side refusal fallbacks, which Donna's replies opt into.
    |
    | Voyage AI is owned by MongoDB: keys come from a MongoDB Atlas account
    | and are only valid against ai.mongodb.com, not api.voyageai.com.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'anthropic_beta' => 'web-fetch-2025-09-10,server-side-fallback-2026-07-01',
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
            'url' => env('VOYAGEAI_URL', 'https://ai.mongodb.com/v1'),
            'models' => [
                'embeddings' => [
                    'default' => env('DONNA_EMBEDDING_MODEL', 'voyage-4'),
                    'dimensions' => 1024,
                ],
            ],
        ],
    ],

];
