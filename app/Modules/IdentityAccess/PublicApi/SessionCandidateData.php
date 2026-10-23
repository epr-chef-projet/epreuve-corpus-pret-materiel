<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class SessionCandidateData
{
    public function __construct(public string $actorId, public Role $role, public string $broadcastToken) {}
}
