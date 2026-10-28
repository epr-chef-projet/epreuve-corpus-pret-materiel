<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use App\Shared\Errors\DomainError;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class IntentClosed extends RuntimeException implements DomainError, ShouldntReport
{
    public const ERROR_CODE = 'operations.intent_closed';

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
