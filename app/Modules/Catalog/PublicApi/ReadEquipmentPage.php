<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentRepository;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;

/** Standalone snapshot or participant in the caller's verified unit; never returns lazy SQL. */
final class ReadEquipmentPage
{
    public function __construct(private SqlUnit $units, private AuthorizeActor $authorize, private EquipmentRepository $equipment) {}

    public function handle(ActorContext $actor, EquipmentQuery $query): EquipmentPage
    {
        if ($query->audience === CatalogAudience::Management) {
            $this->authorize->handle($actor, 'manage-catalog');
        }
        $result = null;
        $this->units->forActorRead($actor, function () use ($actor, $query, &$result): void {
            $result = $this->equipment->page($actor, $query, now()->utc()->format('Y-m-d\TH:i:s.v\Z'));
        });

        return $result ?? throw new \LogicException('Equipment page was not materialized.');
    }
}
