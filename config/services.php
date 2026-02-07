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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'stripe' => [
        'product_id' => env('PRODUCT_ID'),
        'sk_test' => env('STRIPE_SECRET'),
        'pk_test' => env('STRIPE_KEY'),
    ],

    'gemini' => [
        'key' => trim((string) (env('GOOGLE_API_KEY') ?? '')),
    ],

    /*
     * Poppler bin directory for PDF→image (pdf2image). Required when running
     * Python from PHP so the subprocess can find pdftoppm. Set in .env if needed.
     * Example (Windows): POPPLER_PATH=C:\laragon\bin\poppler\Library\bin
     */
    'poppler_path' => env('POPPLER_PATH'),

    'google_document_ai' => [
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'location' => env('GOOGLE_DOCUMENT_AI_LOCATION', 'us'),
        'processor_id' => env('GOOGLE_DOCUMENT_AI_PROCESSOR_ID'),
    ],

];
