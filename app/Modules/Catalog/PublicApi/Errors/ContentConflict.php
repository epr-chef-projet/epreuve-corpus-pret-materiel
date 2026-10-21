<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi\Errors;

use App\Shared\Errors\DomainError;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class ContentConflict extends RuntimeException implements DomainError, ShouldntReport
{
    public function errorCode(): string
    {
        return 'catalog.content_conflict';
    }
}
