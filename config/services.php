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

    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect' => env('AZURE_REDIRECT_URI', env('APP_URL').'/login/microsoft/callback'),
        'tenant' => env('AZURE_TENANT_ID', 'common'),
    ],

    // Azure Cognitive Services / Document Intelligence (OCR for scanned PDFs)
    'azure_ai' => [
        'endpoint' => env('AZURE_AI_ENDPOINT'),
        'key' => env('AZURE_AI_KEY'),
        'ocr_enabled' => env('AZURE_AI_OCR_ENABLED', true),
        'api_version' => env('AZURE_AI_API_VERSION', '2024-11-30'),
        'max_bytes' => env('AZURE_AI_MAX_BYTES', 100 * 1024 * 1024),
        'url_min_bytes' => env('AZURE_AI_URL_MIN_BYTES', 4 * 1024 * 1024),
    ],

    'onlyoffice' => [
        'document_server_url' => env('ONLYOFFICE_DOCUMENT_SERVER_URL'),
        // Base URL OnlyOffice Document Server uses to fetch files and POST save callbacks.
        // Local Docker: http://host.docker.internal:PORT (localhost inside the container is not the host).
        'app_url' => env('ONLYOFFICE_APP_URL'),
        'jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),
    ],

];
