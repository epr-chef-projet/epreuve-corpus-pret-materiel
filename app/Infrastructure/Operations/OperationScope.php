<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class OperationScope
{
    public function __construct(public string $workshopId, public string $actorId, public string $operationType, public string $operationKey)
    {
        foreach ([$workshopId, $actorId, $operationKey] as $id) {
            if (! Str::isUuid($id) || strtolower($id) !== $id) {
                throw new InvalidArgumentException('Invalid operation identity.');
            }
        }
        if (preg_match('/\A(?:catalog|lending|workshop_operations)\.[a-z][a-z0-9_.]{1,100}\z/D', $operationType) !== 1) {
            throw new InvalidArgumentException('Invalid operation type.');
        }
    }
}
