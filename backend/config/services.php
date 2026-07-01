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

    // Synthetic uptime/response monitor.
    'monitor' => [
        'region' => env('MONITOR_REGION', 'default'),
        'timeout' => (int) env('MONITOR_TIMEOUT', 15),
    ],

    // Google Chrome UX Report (real field Web Vitals). Free API key, no OAuth.
    'crux' => [
        'key' => env('CRUX_API_KEY'),
    ],

    // Headless Playwright renderer for the Ad Layout preview.
    'renderer' => [
        'url' => env('RENDERER_URL'),
        'timeout' => (int) env('RENDERER_TIMEOUT', 90),
    ],

    // Prebid analytics ingestion (browser push from publisher sites).
    'prebid' => [
        // Shared secret the Prebid Analytics adapter sends as X-Ingest-Key.
        'ingest_key' => env('PREBID_INGEST_KEY'),
        // Comma-separated publisher origins allowed to POST beacons (CORS).
        'ingest_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PREBID_INGEST_ORIGINS', ''))
        ))),
    ],

    // Security Site Inspector rate limiting (Upstash Redis when configured).
    'security_scan' => [
        // Cache store for rate-limit counters. Set to 'upstash' to use Upstash Redis;
        // null falls back to the default cache store.
        'rate_limit_store' => env('SECURITY_SCAN_RATELIMIT_STORE'),
        'per_minute' => (int) env('SECURITY_SCAN_PER_MINUTE', 3),
        'per_hour' => (int) env('SECURITY_SCAN_PER_HOUR', 30),
    ],

    // Google Ad Manager API (revenue/impressions). Needs OAuth2 service account.
    'gam' => [
        'network_code' => env('GAM_NETWORK_CODE'),
        // Path to the service-account JSON key file, OR the raw JSON content.
        'service_account_json' => env('GAM_SERVICE_ACCOUNT_JSON'),
        'application_name' => env('GAM_APPLICATION_NAME', 'Ads Dashboard Monitoring'),
        // Optional explicit GAM (parent/top) ad unit name -> domain (url or UUID) map.
        // e.g. GAM_DOMAIN_MAP={"Kompas TV":"https://www.kompas.tv","Sonora":"https://www.sonora.id"}
        'domain_map' => json_decode((string) env('GAM_DOMAIN_MAP', '') ?: '{}', true) ?: [],
    ],

];
