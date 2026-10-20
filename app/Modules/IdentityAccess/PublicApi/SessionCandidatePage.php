<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class SessionCandidatePage
{
    /** @param list<SessionCandidateData> $candidates */
    public function __construct(public array $candidates, public ?string $nextCursor) {}
}
