<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use Illuminate\Support\Str;
use InvalidArgumentException;

/** A trusted process supplies this context, never an HTTP payload. */
final readonly class ServiceContext
{
    public function __construct(public string $serviceId, public string $workshopId, public string $capability)
    {
        if (! Str::isUuid($workshopId) || $serviceId === '' || $capability !== 'identity_access.publish') {
            throw new InvalidArgumentException('Invalid service context.');
        }
    }
}
