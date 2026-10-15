<?php

declare(strict_types=1);

return [
    'stage' => 'foundation',
    'browser_tests' => (bool) env('EPR_BROWSER_TESTS', false),
    'job_timeout' => 60,
    'backoff' => [5, 15],
];
