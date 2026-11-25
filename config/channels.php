<?php

return [
    'http' => [
        'connect_timeout' => (int) env('CHANNELS_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('CHANNELS_TIMEOUT', 15),
        'retry_times' => (int) env('CHANNELS_RETRY_TIMES', 2),
        'retry_delay_ms' => (int) env('CHANNELS_RETRY_DELAY_MS', 250),
    ],
    'shopify' => [
        'api_version' => env('SHOPIFY_API_VERSION', '2025-10'),
    ],
];
