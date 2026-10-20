<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog;

use App\Shared\Realtime\NotificationEnvelopeData;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final readonly class CatalogInvalidated implements ShouldBroadcastNow
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
