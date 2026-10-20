<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ValidationPresentationData implements JsonSerializable
{
    /** @param array<string, list<FieldErrorData>> $fieldErrors */
    public function __construct(public string $locale, public array $fieldErrors) {}

    /** @return array{locale: string, fieldErrors: object} */
    public function jsonSerialize(): array
    {
        return ['locale' => $this->locale, 'fieldErrors' => (object) $this->fieldErrors];
    }
}
