<?php

declare(strict_types=1);

namespace Tests\Fixtures\Contracts;

use DateTimeImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ProbeData extends Data
{
    /** @param list<string> $labels */
    public function __construct(
        public string $id,
        public DateTimeImmutable $occurredAt,
        public ProbeState $state,
        public ?string $note,
        public array $labels,
    ) {}
}
