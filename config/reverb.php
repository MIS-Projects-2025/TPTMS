<?php

return [
    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'hostname' => env('REVERB_HOST', '192.168.2.221'),
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 10), // in seconds
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
            'options' => [
                'tls' => [
                    'local_cert' => env('REVERB_LOCAL_CERT'),
                    'local_pk' => env('REVERB_LOCAL_KEY'),
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ],

            'broadcasting' => [
                'base_url' => env('REVERB_BASE_URL', 'https://192.168.2.221:8080'),
                'options' => [
                    'verify' => false, // Explicitly set to false
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ],
                ],
            ],

            'scaling' => [
                'enabled' => false,
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'redis' => [
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', 6379),
                    'database' => env('REDIS_DB', 0),
                ],
            ],

            'apps' => [
                [
                    'key' => env('REVERB_APP_KEY', ''),
                    'secret' => env('REVERB_APP_SECRET', ''),
                    'app_id' => env('REVERB_APP_ID', ''),
                    'options' => [
                        'host' => env('REVERB_HOST', '192.168.2.221'),
                        'port' => env('REVERB_PORT', 8080),
                        'scheme' => env('REVERB_SCHEME', 'https'),
                        'useTLS' => true,
                    ],
                    'allowed_origins' => ['*'],
                    'ping_interval' => 60,
                    'max_message_size' => 10000,
                ],
            ],
        ],
    ],
];
