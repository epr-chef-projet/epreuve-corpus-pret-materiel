<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\JobPayload;
use Laravel\Horizon\Repositories\RedisJobRepository;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Foundation\WriteProbeJob;
use Tests\TestCase;

final class QueueEffectTest extends TestCase
{
    public function test_horizon_keeps_failed_job_diagnostics_for_fourteen_days(): void
    {
        $this->assertEmpty(config('database.redis.default.url'));
        $originalPrefix = config('horizon.prefix');
        $originalConnection = config('database.redis.horizon');
        $this->assertSame('testing_horizon:', $originalPrefix);
        $prefix = $originalPrefix.'retention:'.bin2hex(random_bytes(16)).':';
        $ids = [(string) Str::uuid7(), (string) Str::uuid7(), (string) Str::uuid7()];
        $indexes = ['failed_jobs', 'recent_failed_jobs'];
        $keys = [...$ids, ...$indexes];
        $ownsKeys = false;
        $connection = null;

        try {
            config(['horizon.prefix' => $prefix]);
            Horizon::use('default');
            Redis::purge('horizon');
            $connection = Redis::connection('horizon');

            /** @var \Redis $client */
            $client = $connection->client();
            $this->assertSame('redis', $client->getHost());
            $this->assertSame(14, $client->getDBNum());
            $this->assertSame($prefix, $client->getOption(\Redis::OPT_PREFIX));
            foreach ($keys as $key) {
                $this->assertSame(0, $connection->exists($key));
            }
            $ownsKeys = true;
            $jobs = new RedisJobRepository(app('redis'));

            foreach ($ids as $id) {
                $payload = new JobPayload(json_encode(['uuid' => $id, 'displayName' => 'SyntheticHorizonFailure'], JSON_THROW_ON_ERROR));
                $jobs->failed(new RuntimeException('Synthetic Horizon failure.'), 'redis', 'testing-failures', $payload);
            }

            $failure = $jobs->findFailed($ids[0]);
            $this->assertNotNull($failure);
            $this->assertSame($ids[0], $failure->id);
            $this->assertSame('failed', $failure->status);
            $this->assertSame(['uuid' => $ids[0], 'displayName' => 'SyntheticHorizonFailure'], json_decode($failure->payload, true, flags: JSON_THROW_ON_ERROR));
            $ttl = $connection->ttl($ids[0]);
            $this->assertGreaterThanOrEqual(1209590, $ttl);
            $this->assertLessThanOrEqual(1209600, $ttl);

            $now = time();
            foreach ($indexes as $index) {
                $this->assertCount(3, $connection->zrange($index, 0, -1));
                $connection->zadd($index, -($now - 13 * 86400), $ids[1]);
                $connection->zadd($index, -($now - 15 * 86400), $ids[2]);
            }

            $jobs->trimRecentJobs();
            $jobs->trimFailedJobs();

            foreach ($indexes as $index) {
                $this->assertSame([$ids[0], $ids[1]], $connection->zrange($index, 0, -1));
            }
        } finally {
            try {
                if ($ownsKeys && $connection !== null) {
                    $connection->del(...$keys);
                    foreach ($keys as $key) {
                        $this->assertSame(0, $connection->exists($key));
                    }
                }
            } finally {
                Redis::purge('horizon');
                config(['horizon.prefix' => $originalPrefix, 'database.redis.horizon' => $originalConnection]);
            }
        }
    }

    public function test_failed_redis_jobs_remain_recorded_and_removable_through_the_native_sql_provider(): void
    {
        /** @var FailedJobProviderInterface $failures */
        $failures = app('queue.failer');
        $uuid = (string) Str::uuid7();
        $payload = json_encode(['uuid' => $uuid, 'displayName' => 'SyntheticFailure'], JSON_THROW_ON_ERROR);

        DB::beginTransaction();
        try {
            $this->assertSame($uuid, $failures->log('redis', 'testing-failures', $payload, new RuntimeException('Synthetic queue failure.')));
            $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid, 'connection' => 'redis', 'queue' => 'testing-failures', 'payload' => $payload]);
            $this->assertNotNull($failures->find($uuid));
            $this->assertTrue($failures->forget($uuid));
            $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_horizon_processes_a_real_redis_job_and_writes_decryptable_private_output(): void
    {
        $this->assertEmpty(config('database.redis.default.url'));
        /** @var \Redis $client */
        $client = Redis::connection()->client();
        $this->assertSame('redis', $client->getHost());
        $this->assertSame(14, $client->getDBNum());
        $this->assertSame('epr_testing_', $client->getOption(\Redis::OPT_PREFIX));

        $token = bin2hex(random_bytes(16));
        $path = 'foundation/'.$token.'.enc';
        $worker = new Process([PHP_BINARY, 'artisan', 'horizon'], base_path(), array_filter($_ENV, is_string(...)));
        $worker->setTimeout(30);
        try {
            $worker->start();
            WriteProbeJob::dispatch($token);
            $deadline = microtime(true) + 15;
            do {
                if (Storage::disk('local')->exists($path)) {
                    break;
                }
                $this->assertTrue($worker->isRunning(), 'Test Horizon exited: '.$worker->getErrorOutput());
                usleep(100000);
            } while (microtime(true) < $deadline);
            $this->assertTrue(Storage::disk('local')->exists($path), 'Queued intent never became a private file effect.');
            $contents = Storage::disk('local')->get($path);
            $this->assertNotNull($contents);
            $this->assertSame('foundation-'.$token, Crypt::decryptString($contents));
        } finally {
            $worker->stop(20, SIGTERM);
            Storage::disk('local')->delete($path);
        }
    }
}
