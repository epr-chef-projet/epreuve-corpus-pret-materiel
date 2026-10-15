<?php

declare(strict_types=1);

return [
    'key' => env('REVERB_APP_KEY'),
    'host' => env('REVERB_PUBLIC_HOST'),
    'port' => (int) env('REVERB_PUBLIC_PORT', 443),
    'scheme' => env('REVERB_PUBLIC_SCHEME', 'https'),
];
