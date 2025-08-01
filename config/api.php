<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | This value is the current version of your API. This version string
    | will be returned in API responses via the X-API-Version header.
    |
    */
    'version' => env('API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | API Documentation
    |--------------------------------------------------------------------------
    |
    | Configuration for API documentation generation and display.
    |
    */
    'documentation' => [
        'title' => env('API_DOC_TITLE', 'Archive Master API'),
        'description' => env('API_DOC_DESCRIPTION', 'API para el sistema de gestión documental Archive Master'),
        'version' => env('API_DOC_VERSION', '1.0.0'),
        'contact' => [
            'name' => env('API_CONTACT_NAME', 'Archive Master Team'),
            'email' => env('API_CONTACT_EMAIL', 'support@archivemaster.com'),
        ],
        'license' => [
            'name' => env('API_LICENSE_NAME', 'MIT'),
            'url' => env('API_LICENSE_URL', 'https://opensource.org/licenses/MIT'),
        ],
        'servers' => [
            [
                'url' => env('API_SERVER_URL', 'http://localhost:8000/api'),
                'description' => env('API_SERVER_DESCRIPTION', 'Servidor de desarrollo'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for API rate limiting. These values are used by the
    | ApiResponseMiddleware to add rate limit headers to responses.
    |
    */
    'rate_limiting' => [
        'default_limit' => env('API_RATE_LIMIT', 60),
        'default_window' => env('API_RATE_WINDOW', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-Origin Resource Sharing (CORS) configuration for API endpoints.
    |
    */
    'cors' => [
        'allowed_origins' => explode(',', env('API_CORS_ORIGINS', '*')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => [
            'Accept',
            'Authorization',
            'Content-Type',
            'X-Requested-With',
            'X-API-Version',
        ],
        'exposed_headers' => [
            'X-API-Version',
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining',
            'X-RateLimit-Reset',
        ],
        'max_age' => 86400, // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    |
    | Default response format configuration for API endpoints.
    |
    */
    'response' => [
        'default_per_page' => env('API_DEFAULT_PER_PAGE', 15),
        'max_per_page' => env('API_MAX_PER_PAGE', 100),
        'include_meta' => env('API_INCLUDE_META', true),
        'include_links' => env('API_INCLUDE_LINKS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for API endpoints.
    |
    */
    'security' => [
        'token_expiration' => env('API_TOKEN_EXPIRATION', 60 * 24), // minutes (24 hours)
        'max_login_attempts' => env('API_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('API_LOCKOUT_DURATION', 15), // minutes
    ],
];