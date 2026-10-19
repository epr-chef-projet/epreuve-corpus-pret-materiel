<?php

declare(strict_types=1);

return [
    'service_id' => env('IDENTITY_SERVICE_ID', ''),
    'service_capabilities' => array_filter(explode(',', (string) env('IDENTITY_SERVICE_CAPABILITIES', ''))),
];
