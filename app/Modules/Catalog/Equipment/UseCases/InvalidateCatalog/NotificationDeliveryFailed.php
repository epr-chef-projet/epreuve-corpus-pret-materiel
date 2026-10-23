<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Keeps the native cause in memory and selects its representation for queue diagnostics. */
final class NotificationDeliveryFailed extends RuntimeException
{
    public function __construct(private readonly string $workshopId, private readonly string $correlationId, Throwable $previous)
    {
        parent::__construct('Catalogue notification delivery failed.', previous: $previous);
    }

    /** @return array<string, bool|int|string|null> */
    public function diagnostic(): array
    {
        $cause = $this->getPrevious();

        return ['errorCode' => 'catalog.notification_delivery_failed', 'environment' => app()->environment(), 'workshopId' => Str::isUuid($this->workshopId) ? $this->workshopId : null, 'correlationId' => Str::isUuid($this->correlationId) ? $this->correlationId : null, 'exceptionClass' => $cause !== null ? $cause::class : null, 'nativeCode' => $cause?->getCode(), 'sqlState' => $cause instanceof QueryException ? ($cause->errorInfo[0] ?? null) : null, 'causeFile' => $cause !== null ? basename($cause->getFile()) : null, 'causeLine' => $cause?->getLine(), 'causeMessageOmitted' => true];
    }

    public function report(): bool
    {
        try {
            Log::channel('catalog')->error('catalog.notification_failed', $this->diagnostic());
        } catch (Throwable) {
            error_log(json_encode(['message' => 'runtime.collector_unavailable', 'context' => $this->diagnostic()], JSON_THROW_ON_ERROR));
        }

        return true;
    }

    public function __toString(): string
    {
        return json_encode($this->diagnostic(), JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES) ?: 'catalog.notification_delivery_failed';
    }
}
