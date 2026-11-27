<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Josh's Timezone
    |--------------------------------------------------------------------------
    |
    | Dates Donna sees, the daily extraction schedule, and "this month" in the
    | cost report are all in this timezone. Storage stays UTC.
    |
    */

    'timezone' => env('DONNA_TIMEZONE', 'America/Chicago'),

    /*
    |--------------------------------------------------------------------------
    | The One User
    |--------------------------------------------------------------------------
    |
    | Seeded on first deploy. There is no password: the web app signs in with
    | a code emailed to this address, and the terminal with the Sanctum token
    | `donna:token` prints. No other address can sign in.
    |
    */

    'user' => [
        'name' => env('DONNA_USER_NAME', 'Josh'),
        'email' => env('DONNA_USER_EMAIL', 'josh@heydonna.app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signing In
    |--------------------------------------------------------------------------
    |
    | A sign-in code and its link work once, for `code_minutes`, and die after
    | `max_attempts` wrong guesses. A web session lasts SESSION_LIFETIME
    | minutes from its last use, and never longer than `session_max_days`
    | from sign-in, however much it is used.
    |
    */

    'login' => [
        'code_minutes' => 10,
        'max_attempts' => 5,
        'session_max_days' => (int) env('DONNA_SESSION_MAX_DAYS', 90),

        // How long the "Sign out everywhere" link in the new-sign-in email works.
        'revoke_link_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push
    |--------------------------------------------------------------------------
    |
    | The VAPID keys that identify this server to browsers' push services
    | (`php artisan webpush:vapid --show` makes a pair). Without them push is
    | off and everything else works. A reply is only pushed when the app has
    | not polled within `open_seconds`: if it has, Josh is looking at it.
    |
    */

    'push' => [
        'vapid' => [
            'subject' => env('VAPID_SUBJECT'),
            'public_key' => env('VAPID_PUBLIC_KEY'),
            'private_key' => env('VAPID_PRIVATE_KEY'),
        ],
        'open_seconds' => 15,
        'body_characters' => 140,
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminal Client
    |--------------------------------------------------------------------------
    |
    | `donna:chat` runs on Josh's Mac and talks to the deployed API with these.
    | The server itself never reads them.
    |
    */

    'client' => [
        'url' => env('DONNA_API_URL'),
        'token' => env('DONNA_API_TOKEN'),
        'history' => 10,
        'poll_seconds' => 1,
        'wait_seconds' => 600,

        // The function key that sends a message, besides Ctrl+D: f1 to f12, or
        // blank for none. Meant for phone terminals, where Ctrl+D is awkward.
        'submit_key' => env('DONNA_SUBMIT_KEY', 'f5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | One entry per job. The embedding dimensions are also baked into the
    | vector columns, so changing them needs a migration and a re-embed.
    |
    */

    'models' => [
        'reply' => [
            'provider' => 'anthropic',
            'model' => env('DONNA_REPLY_MODEL', 'claude-sonnet-5-5'),
            'effort' => env('DONNA_REPLY_EFFORT', 'medium'),
            'max_tokens' => 16000,
            'max_steps' => 8,

            // Seconds allowed for one model call, and for the whole reply.
            'timeout' => 120,
            'job_timeout' => 600,

            // Anthropic's server-side fallback retries some declined requests on
            // another model. It is a beta; turn it off here if it ever changes.
            'refusal_fallback' => (bool) env('DONNA_REFUSAL_FALLBACK', true),
        ],

        'tagging' => [
            'provider' => 'anthropic',
            'model' => env('DONNA_TAGGING_MODEL', 'claude-haiku-5-5'),
            'effort' => 'low',
            'max_tokens' => 2000,
            'timeout' => 60,
        ],

        'extraction' => [
            'provider' => 'anthropic',
            'model' => env('DONNA_EXTRACTION_MODEL', 'claude-sonnet-5-5'),
            'effort' => 'medium',
            'max_tokens' => 32000,
        ],

        'embedding' => [
            'provider' => 'voyageai',
            'model' => env('DONNA_EMBEDDING_MODEL', 'voyage-4'),
            'dimensions' => 1024,
        ],
    ],

    'web_search' => [
        'max_uses' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Assembly
    |--------------------------------------------------------------------------
    |
    | Every count and threshold the ContextBuilder uses. Token budgets are
    | estimated at four characters per token; they cap prompt size, not cost.
    |
    */

    'context' => [
        'recent_messages' => 20,
        'recent_token_budget' => 8000,
        'memory_token_budget' => 10000,

        'search_candidates' => 40,
        'rrf_k' => 60,
        'related_messages' => 12,
        'related_facts' => 20,
        'project_boost' => 0.01,

        'people_top_facts' => 5,
        'nearest_topics' => 5,
        'topic_max_distance' => 0.6,
        'stale_topic_days' => 14,
        'mention_windows' => [7, 30, 90],

        // "What I looked at": how many context items a reply lists, and how
        // many of those places are kept for people and topics.
        'sources' => 8,
        'sources_named' => 2,

        'recall_limit' => 15,
        'what_i_know_limit' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Topic Tagging
    |--------------------------------------------------------------------------
    */

    'tagging' => [
        'candidate_topics' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Extraction
    |--------------------------------------------------------------------------
    |
    | Runs at `at` in Josh's timezone. Facts under `min_confidence` are still
    | saved with their confidence, so Donna can confirm them when they come up.
    |
    */

    'extraction' => [
        'at' => '03:00',
        'poll_minutes' => 10,
        'related_facts_per_request' => 60,
        'min_confidence' => 0.6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | USD per million tokens, from Anthropic's and Voyage's pricing pages as of
    | November 2026. Cache writes use the 5-minute rate (1.25x input). A model
    | ID reported with a date suffix matches its undated entry.
    |
    | `long_context` applies the higher card to a whole request whose prompt
    | (input including cache reads and writes) exceeds the threshold.
    |
    */

    'pricing' => [
        'batch_discount' => 0.5,
        'web_search_per_request' => 0.01,

        'models' => [
            'claude-sonnet-5-5' => [
                'input' => 2.00,
                'cache_write' => 2.50,
                'cache_read' => 0.10,
                'output' => 10.00,
            ],

            // Where Sonnet 5.5's server-side refusal fallback can land.
            'claude-sonnet-5' => [
                'input' => 2.00,
                'cache_write' => 2.50,
                'cache_read' => 0.20,
                'output' => 10.00,
            ],

            'claude-haiku-5-5' => [
                'input' => 0.10,
                'cache_write' => 0.125,
                'cache_read' => 0.01,
                'output' => 0.50,
                'long_context' => [
                    'threshold' => 100_000,
                    'input' => 0.50,
                    'cache_write' => 0.625,
                    'cache_read' => 0.05,
                    'output' => 2.50,
                ],
            ],

            // The first 200M tokens are free; priced at list so the number
            // stays honest once the free tier runs out.
            'voyage-4' => [
                'input' => 0.06,
                'cache_write' => 0.0,
                'cache_read' => 0.0,
                'output' => 0.0,
            ],
        ],
    ],

];
