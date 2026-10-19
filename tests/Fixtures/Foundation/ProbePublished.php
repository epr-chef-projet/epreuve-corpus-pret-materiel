<?php

declare(strict_types=1);

namespace Tests\Fixtures\Foundation;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final readonly class ProbePublished implements ShouldBroadcastNow
{
    public function __construct(public string $userId, public string $marker) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('foundation.probe.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'ProbePublished';
    }

    /** @return array{marker: string} */
    public function broadcastWith(): array
    {
        return ['marker' => $this->marker];
    }
}
