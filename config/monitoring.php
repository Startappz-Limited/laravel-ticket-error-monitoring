<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | When false the SDK is fully inert — no capture, no network calls.
    */
    'enabled' => env('MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Credentials & endpoint
    |--------------------------------------------------------------------------
    | Issued when you register the application in the TicketGo admin panel.
    | `api_key` is the public key (sent as X-Monitor-Key); `secret` signs the
    | request body (HMAC-SHA256 → X-Monitor-Signature).
    */
    'api_key' => env('MONITORING_API_KEY'),
    'secret' => env('MONITORING_SECRET'),
    'endpoint' => env('MONITORING_ENDPOINT'),

    /*
    |--------------------------------------------------------------------------
    | Application metadata
    |--------------------------------------------------------------------------
    */
    'environment' => env('APP_ENV', 'production'),
    'release' => env('MONITORING_RELEASE'),
    'tenant_id' => env('MONITORING_TENANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    | `send_after_response` defers the HTTP call to the framework's terminating
    | phase so reporting never adds latency to the user's request.
    */
    'timeout' => env('MONITORING_TIMEOUT', 3),
    'verify_ssl' => env('MONITORING_VERIFY_SSL', true),
    'send_after_response' => env('MONITORING_DEFERRED', true),

    /*
    |--------------------------------------------------------------------------
    | What to capture
    |--------------------------------------------------------------------------
    */
    'capture' => [
        'exceptions' => true,
        'queue_failures' => true,
        'slow_queries' => false,
        'slow_query_threshold' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Data protection
    |--------------------------------------------------------------------------
    | Keys matched here (case-insensitive substring) are redacted from request
    | payloads, headers and context before anything leaves the host app.
    */
    'sanitize' => [
        'fields' => [
            'password', 'passwd', 'secret', 'token', 'authorization', 'auth',
            'api_key', 'apikey', 'access_key', 'private_key', 'credit_card',
            'card_number', 'cvv', 'ssn', 'cookie',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exceptions never reported
    |--------------------------------------------------------------------------
    | Fully-qualified class names to ignore (and their subclasses).
    */
    'dont_report' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],
];
