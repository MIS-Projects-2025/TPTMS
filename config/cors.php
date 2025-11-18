<?php

return [
    'paths' => ['api/*', 'TPTMS/*', 'broadcasting/auth', 'tickets/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://192.168.2.221:85',
        'https://192.168.2.221:8080',
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
