<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class IssueData implements JsonSerializable
{
    public string $messageKey;

    /**
     * @param  array<string, int|string>  $parameters
     * @param  array<string, list<FieldErrorData>>  $fieldErrors
     */
    public function __construct(
        public string $errorCode,
        public array $parameters,
        public string $correlationId,
        #[LiteralTypeScriptType("'notApplied'|'applied'|'unknown'")] public string $outcome,
        public NextActionData $nextAction,
        #[Optional] public array $fieldErrors = [],
    ) {
        if (! in_array($outcome, ['notApplied', 'applied', 'unknown'], true)) {
            throw new \InvalidArgumentException('Invalid issue outcome.');
        }
        $this->messageKey = 'issues.'.$errorCode;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $result = ['errorCode' => $this->errorCode, 'messageKey' => $this->messageKey, 'parameters' => (object) $this->parameters,
            'correlationId' => $this->correlationId, 'outcome' => $this->outcome, 'nextAction' => $this->nextAction];
        if ($this->fieldErrors !== []) {
            $result['fieldErrors'] = (object) $this->fieldErrors;
        }

        return $result;
    }
}
