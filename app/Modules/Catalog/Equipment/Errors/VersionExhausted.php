<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\Errors;

use App\Shared\Errors\DomainError;
use RuntimeException;

final class VersionExhausted extends RuntimeException implements DomainError
{
    public function errorCode(): string
    {
        return 'catalog.version_exhausted';
    }
}
