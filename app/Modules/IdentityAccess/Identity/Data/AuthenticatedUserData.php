<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Identity\Data;

use App\Modules\IdentityAccess\PublicApi\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AuthenticatedUserData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $login,
        public readonly string $displayName,
        public readonly string $workshopId,
        public readonly string $workshopName,
        public readonly Role $role,
    ) {}
}
