<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Foundation\WriteProbeJob;
use Tests\TestCase;

final class QueueEffectTest extends TestCase
{
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
