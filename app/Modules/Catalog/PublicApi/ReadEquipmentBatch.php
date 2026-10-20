<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentRepository;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Server callers own the relevance of archived references to already authorized lines. */
final class ReadEquipmentBatch
{
    public function __construct(private SqlUnit $units, private EquipmentRepository $equipment) {}

    /** @param array<array-key, mixed> $ids Dense list of at most 100 distinct canonical UUIDs.
     * @return list<EquipmentData>
     */
    public function handle(ActorContext $actor, array $ids): array
    {
        if (! array_is_list($ids) || count($ids) > 100) {
            throw new InvalidArgumentException('Equipment batch must contain at most 100 distinct UUIDs.');
        }
        foreach ($ids as $id) {
            if (! is_string($id) || ! Str::isUuid($id) || strtolower($id) !== $id) {
                throw new InvalidArgumentException('Invalid equipment UUID.');
            }
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Equipment UUIDs must be distinct.');
        }
        $result = [];
        $this->units->forActorRead($actor, function () use ($actor, $ids, &$result): void {
            $result = $this->equipment->batch($actor, $ids);
        });

        return $result;
    }
}
