<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi\Errors;

use App\Shared\Errors\DomainError;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class TotalBelowCommitments extends RuntimeException implements DomainError, ShouldntReport
{
    public function __construct(public readonly int $minimumQuantity)
    {
        parent::__construct('Equipment total is below protected commitments.');
    }

    public function errorCode(): string
    {
        return 'catalog.total_below_commitments';
    }
}
