<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Foundation\WriteProbeJob;
use Tests\TestCase;

final class QueueEffectTest extends TestCase
{
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
