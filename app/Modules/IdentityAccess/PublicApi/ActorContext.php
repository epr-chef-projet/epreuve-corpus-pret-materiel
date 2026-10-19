<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ActorContext
{
    public function __construct(public string $actorId, public string $workshopId, public string $role)
    {
        if (! Str::isUuid($actorId) || ! Str::isUuid($workshopId) || ! in_array($role, ['borrower', 'staff', 'manager'], true)) {
            throw new InvalidArgumentException('Invalid actor context.');
        }
    }
}
