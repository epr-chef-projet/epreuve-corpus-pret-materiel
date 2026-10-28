<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipment;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\Catalog\PublicApi\EquipmentData;
use App\Modules\Catalog\PublicApi\EquipmentQuery;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\Gate;

final class ReadEquipmentAction
{
    public function __construct(private SqlUnit $units, private AuthorizeActor $authorize, private EquipmentRepository $equipment) {}

    public function managementPage(ActorContext $actor, EquipmentQuery $query): ManagementPageData
    {
        $this->authorize->handle($actor, 'manage-catalog');
        $query = new EquipmentQuery($query->search, $query->category, $query->page, $query->perPage, CatalogAudience::Management);
        $result = null;
        $this->units->forActorRead($actor, function () use ($actor, $query, &$result): void {
            Gate::forUser(null)->authorize('viewAny', [Equipment::class, $actor, CatalogAudience::Management]);
            $result = $this->equipment->managementPage($actor, $query, now()->utc()->format('Y-m-d\TH:i:s.v\Z'));
        });

        return $result ?? throw new \LogicException('Equipment management page was not materialized.');
    }

    public function handle(ActorContext $actor, string $id): EquipmentData
    {
        $this->authorize->handle($actor, 'manage-catalog');
        Gate::forUser(null)->authorize('viewAny', [Equipment::class, $actor, CatalogAudience::Management]);
        $result = null;
        $this->units->forActorRead($actor, function () use ($actor, $id, &$result): void {
            $result = $this->equipment->find($actor, $id);
        });

        return $result ?? throw new \LogicException('Equipment detail was not materialized.');
    }
}
