<?php

declare(strict_types=1);

namespace Tests\Fixtures\Foundation;

use App\Shared\Realtime\NotificationEnvelopeData;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final readonly class SessionProbePublished implements ShouldBroadcastNow
{
    public function __construct(private string $channel, private NotificationEnvelopeData $envelope) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'epr.notification';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->envelope->jsonSerialize();
    }
}
