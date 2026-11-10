<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Gemini. The key never leaves the server. The base URL is the v1beta REST root: ephemeral Live tokens
    | only work with v1beta (docs/phase-0-findings.md), and the Interactions API lives there too.
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'timeout' => (int) env('GEMINI_HTTP_TIMEOUT', 20),
        // Browsers connect here with an ephemeral token (never the key). Verified in Phase 0 (S1, S2).
        'live_ws_url' => 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained',
        'live_token_ttl' => (int) env('GEMINI_LIVE_TOKEN_TTL_SECONDS', 720),
        'live_new_session_ttl' => (int) env('GEMINI_LIVE_NEW_SESSION_TTL_SECONDS', 60),
    ],

];
