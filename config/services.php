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

    'google' => [
        // OAuth client for "Continue with Google". Leave the ID empty to hide the button.
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // A relative path is resolved against the app URL at runtime.
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'ai' => [
        // Primary AI provider that decomposes & verifies foods: 'gemini' or 'groq'.
        'provider' => env('AI_PROVIDER', 'gemini'),
        // Secondary provider used automatically when the primary is rate-limited.
        // Set empty to disable fail-over.
        'fallback' => env('AI_FALLBACK_PROVIDER', 'groq'),
        // Minutes to keep routing to the fallback after the primary hits its quota.
        'fallback_cooldown' => (int) env('AI_FALLBACK_COOLDOWN_MINUTES', 30),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        // Vision model used for photo capture (must be multimodal).
        'vision_model' => env('GROQ_VISION_MODEL', 'qwen/qwen3.8-27b'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        // Gemini Flash is multimodal, so the same model handles photo capture.
        'vision_model' => env('GEMINI_VISION_MODEL', env('GEMINI_MODEL', 'gemini-3.5-flash')),
    ],

    'usda' => [
        'key' => env('USDA_API_KEY') ?: 'DEMO_KEY',
    ],

];
