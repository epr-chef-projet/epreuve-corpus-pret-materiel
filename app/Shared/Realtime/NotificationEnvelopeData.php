<?php

declare(strict_types=1);

namespace App\Shared\Realtime;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class NotificationEnvelopeData implements JsonSerializable
{
    #[LiteralTypeScriptType('1')]
    public int $contractVersion;

    /** @param array<string, mixed> $payload A selected JSON value; each projection owner validates its own payload. */
    public function __construct(
        public string $eventId,
        public int $authGeneration,
        #[LiteralTypeScriptType("'request.snapshot'|'document.snapshot'|'catalog.invalidated'|'requests.invalidated'")]
        public string $type,
        #[LiteralTypeScriptType('Record<string, unknown>')] public array $payload,
    ) {
        if ($authGeneration < 1 || $authGeneration > 2147483647 || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $eventId) !== 1
            || ! in_array($type, ['request.snapshot', 'document.snapshot', 'catalog.invalidated', 'requests.invalidated'], true)
            || ! self::jsonValue($payload, 0)) {
            throw new \InvalidArgumentException('Invalid notification envelope.');
        }
        $this->contractVersion = 1;
        if (strlen(json_encode($this, JSON_THROW_ON_ERROR)) > 16 * 1024) {
            throw new \InvalidArgumentException('Notification exceeds its transport budget.');
        }
    }

    /** @return array{eventId: string, contractVersion: int, authGeneration: int, type: string, payload: object} */
    public function jsonSerialize(): array
    {
        return ['eventId' => $this->eventId, 'contractVersion' => $this->contractVersion, 'authGeneration' => $this->authGeneration, 'type' => $this->type, 'payload' => (object) $this->payload];
    }

    private static function jsonValue(mixed $value, int $depth): bool
    {
        if ($depth > 16) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::jsonValue($item, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_string($value) || is_bool($value) || is_int($value) || (is_float($value) && is_finite($value));
    }
}
