<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\Data;

use App\Modules\IdentityAccess\Identity\Data\AuthenticatedUserData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SessionContextData extends Data
{
    public function __construct(
        public readonly ?AuthenticatedUserData $user,
        public readonly ?string $broadcastToken,
        public readonly ?int $authGeneration,
        public readonly ?string $expiresAt,
        public readonly string $locale,
    ) {}
}
