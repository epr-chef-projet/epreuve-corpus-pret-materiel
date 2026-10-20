<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class FieldErrorData implements JsonSerializable
{
    /** @param array<string, int|string> $parameters */
    public function __construct(public string $messageKey, public array $parameters = []) {}

    /** @return array{messageKey: string, parameters: object} */
    public function jsonSerialize(): array
    {
        return ['messageKey' => $this->messageKey, 'parameters' => (object) $this->parameters];
    }
}
