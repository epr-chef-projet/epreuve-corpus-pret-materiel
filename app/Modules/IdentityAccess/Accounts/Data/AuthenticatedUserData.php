<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Accounts\Data;

use App\Modules\IdentityAccess\PublicApi\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AuthenticatedUserData extends Data
{
    public function __construct(
        public string $id,
        public string $login,
        public string $displayName,
        public string $workshopId,
        public string $workshopName,
        public Role $role,
    ) {}
}
