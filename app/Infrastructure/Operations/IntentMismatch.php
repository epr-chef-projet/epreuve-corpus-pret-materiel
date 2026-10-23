<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use App\Shared\Errors\DomainError;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class IntentMismatch extends RuntimeException implements DomainError, ShouldntReport
{
    public function errorCode(): string
    {
        return 'operations.intent_mismatch';
    }
}
