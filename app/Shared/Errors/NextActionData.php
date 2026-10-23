<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class NextActionData implements JsonSerializable
{
    public string $messageKey;

    public function __construct(
        #[LiteralTypeScriptType("'correctInput'|'reload'|'signIn'|'verifyOperation'|'retrySameOperation'|'contactStaff'|'contactManager'")]
        public string $kind,
    ) {
        if (! in_array($kind, ['correctInput', 'reload', 'signIn', 'verifyOperation', 'retrySameOperation', 'contactStaff', 'contactManager'], true)) {
            throw new \InvalidArgumentException('Invalid issue next action.');
        }
        $this->messageKey = 'actions.'.$kind;
    }

    /** @return array{kind: string, messageKey: string} */
    public function jsonSerialize(): array
    {
        return ['kind' => $this->kind, 'messageKey' => $this->messageKey];
    }
}
