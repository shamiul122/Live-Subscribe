<?php

return [
    'social' => [
        'facebook' => [
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
            'app_secret' => env('FACEBOOK_APP_SECRET'),
            'authorize_url' => env('FACEBOOK_AUTHORIZE_URL', 'https://www.facebook.com/v20.0/dialog/oauth'),
            'token_url' => env('FACEBOOK_TOKEN_URL', 'https://graph.facebook.com/v20.0/oauth/access_token'),
            'api_base_url' => env('FACEBOOK_API_BASE_URL', 'https://graph.facebook.com/v20.0'),
            'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN'),
            'redirect_uri' => env('FACEBOOK_REDIRECT_URI'),
        ],
        'instagram' => [
            'client_id' => env('INSTAGRAM_CLIENT_ID'),
            'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
            'app_secret' => env('INSTAGRAM_APP_SECRET'),
            'api_base_url' => env('INSTAGRAM_API_BASE_URL', 'https://graph.instagram.com'),
            'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
            'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),
        ],
        'whatsapp' => [
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'app_secret' => env('WHATSAPP_APP_SECRET'),
            'api_base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com/v20.0'),
            'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        ],
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),
            'webhook_secret_token' => env('TELEGRAM_WEBHOOK_SECRET_TOKEN'),
        ],
        'email' => [
            'api_key' => env('EMAIL_API_KEY'),
            'messaging_service_sid' => env('EMAIL_MESSAGING_SERVICE_SID'),
            'from_address' => env('EMAIL_FROM_ADDRESS'),
            'api_base_url' => env('EMAIL_API_BASE_URL'),
            'webhook_verify_token' => env('EMAIL_WEBHOOK_VERIFY_TOKEN'),
        ],
        'redirect_uri' => env('SOCIAL_REDIRECT_URI'),
    ],
];
