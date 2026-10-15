<?php

declare(strict_types=1);

namespace Tests\Fixtures\Contracts;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ProbeState: string
{
    case Ready = 'ready';
    case Complete = 'complete';
}
