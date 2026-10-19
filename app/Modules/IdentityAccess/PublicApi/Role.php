<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Role: string
{
    case Borrower = 'borrower';
    case Staff = 'staff';
    case Manager = 'manager';

    public function allows(string $ability): bool
    {
        return match ($ability) {
            'borrow' => $this === self::Borrower,
            'operate-loans' => in_array($this, [self::Staff, self::Manager], true),
            'manage-catalog' => $this === self::Manager,
            default => false,
        };
    }
}
