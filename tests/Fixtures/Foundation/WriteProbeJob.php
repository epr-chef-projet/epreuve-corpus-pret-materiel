<?php

declare(strict_types=1);

namespace Tests\Fixtures\Foundation;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

final class WriteProbeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(public readonly string $token)
    {
        $this->onConnection('redis')->onQueue('testing-files');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15];
    }

    public function handle(): void
    {
        Storage::disk('local')->put('foundation/'.$this->token.'.enc', Crypt::encryptString('foundation-'.$this->token));
    }
}
