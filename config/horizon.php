<?php

declare(strict_types=1);

return [
    'domain' => null,
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => env('APP_ENV') === 'testing' ? 'testing_horizon:' : env('EPR_INSTANCE', 'unconfigured').'_horizon:',
    'middleware' => ['web'],
    'waits' => ['redis:files' => 60, 'redis:notifications' => 60, 'redis:maintenance' => 60],
    'trim' => ['recent' => 60, 'pending' => 60, 'completed' => 60, 'recent_failed' => 20160, 'failed' => 20160, 'monitored' => 60],
    'silenced' => [],
    'metrics' => ['trim_snapshots' => ['job' => 24, 'queue' => 24]],
    'fast_termination' => false,
    'memory_limit' => 64,
    'defaults' => [
        'foundation' => [
            'connection' => 'redis',
            'queue' => ['files', 'notifications', 'maintenance'],
            'balance' => 'simple',
            'processes' => 1,
            'maxProcesses' => 1,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => 3,
            'backoff' => [5, 15],
            'timeout' => 65,
            'nice' => 0,
        ],
    ],
    'environments' => [
        'local' => ['foundation' => []],
        'testing' => ['foundation' => ['queue' => ['testing-files', 'testing-notifications', 'testing-maintenance']]],
        'production' => ['foundation' => []],
    ],
];
