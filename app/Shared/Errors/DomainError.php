<?php

declare(strict_types=1);

namespace App\Shared\Errors;

interface DomainError
{
    public function errorCode(): string;
}
