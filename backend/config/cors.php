<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Restrict allowed origins to the configured frontend URL. Never use a
    | wildcard origin in production (SECURITY.md §6).
    |
    */

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_merge(
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')),
        // Publisher origins allowed to POST Prebid telemetry beacons.
        explode(',', (string) env('PREBID_INGEST_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
