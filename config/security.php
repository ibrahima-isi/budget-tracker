<?php

return [
    'login' => [
        'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_seconds' => env('LOGIN_LOCKOUT_SECONDS', 300),
    ],

    'dynamic_requests' => [
        'enabled' => env('DYNAMIC_RATE_LIMIT_ENABLED', true),
        'ip_attempts' => env('DYNAMIC_RATE_LIMIT_IP_ATTEMPTS', 120),
        'network_attempts' => env('DYNAMIC_RATE_LIMIT_NETWORK_ATTEMPTS', 600),
        'window_seconds' => env('DYNAMIC_RATE_LIMIT_WINDOW_SECONDS', 60),
        'block_seconds' => env('DYNAMIC_RATE_LIMIT_BLOCK_SECONDS', 900),
        'excluded_paths' => [
            'health',
            'up',
        ],
    ],
];
