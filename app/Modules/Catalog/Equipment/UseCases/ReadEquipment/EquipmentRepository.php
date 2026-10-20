<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipment;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\Catalog\PublicApi\EquipmentData;
use App\Modules\Catalog\PublicApi\EquipmentPage;
use App\Modules\Catalog\PublicApi\EquipmentQuery;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EquipmentRepository
{
    private const COLUMNS = ['id', 'workshop_id', 'name', 'description', 'category', 'total_quantity', 'is_active', 'version'];

    public function __construct(private SqlUnit $units) {}

    /** @return Builder<Equipment> */
    private function query(ActorContext $actor): Builder
    {
        $this->units->assertActor(DB::connection('pgsql'), $actor);

        return Equipment::query()->where('workshop_id', $actor->workshopId);
    }

    public function page(ActorContext $actor, EquipmentQuery $query, string $observedAt): EquipmentPage
    {
        $sql = $this->query($actor);
        if ($query->audience === CatalogAudience::Catalog) {
            $sql->where('is_active', true);
        }
        if ($query->search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->search);
            $sql->whereRaw("lower(name) LIKE lower(?) ESCAPE E'\\\\'", ['%'.$escaped.'%']);
        }
        if ($query->category !== null) {
            $sql->where('category', $query->category);
        }
        $page = $sql->orderBy('name')->orderBy('id')->simplePaginate($query->perPage, self::COLUMNS, 'page', $query->page);

        return new EquipmentPage(array_values(array_map($this->data(...), $page->items())), $query->page, $query->perPage, $page->hasMorePages(), $observedAt);
    }

    /** @param list<string> $ids
     * @return list<EquipmentData>
     */
    public function batch(ActorContext $actor, array $ids): array
    {
        $rows = $this->query($actor)->whereIn('id', $ids)->orderBy('id')->get(self::COLUMNS);

        return array_values($rows->map($this->data(...))->all());
    }

    public function find(ActorContext $actor, string $id): EquipmentData
    {
        return $this->data($this->query($actor)->select(self::COLUMNS)->findOrFail($id));
    }

    public function lock(ActorContext $actor, string $id): Equipment
    {
        return $this->query($actor)->select(self::COLUMNS)->lockForUpdate()->findOrFail($id);
    }

    public function insert(ActorContext $actor, EquipmentInput $input, int $totalQuantity): Equipment
    {
        $this->units->assertActor(DB::connection('pgsql'), $actor);
        $equipment = new Equipment;
        $equipment->id = (string) Str::uuid7();
        $equipment->workshop_id = $actor->workshopId;
        $equipment->name = $input->name;
        $equipment->description = $input->description;
        $equipment->category = $input->category;
        $equipment->total_quantity = $totalQuantity;
        $equipment->version = 1;
        $equipment->is_active = true;
        $equipment->save();

        return $equipment;
    }

    public function save(ActorContext $actor, Equipment $equipment): void
    {
        $this->units->assertActor(DB::connection('pgsql'), $actor);
        $equipment->save();
    }

    private function data(Equipment $equipment): EquipmentData
    {
        return new EquipmentData($equipment->id, $equipment->name, $equipment->description, $equipment->category, $equipment->total_quantity, $equipment->is_active ? 'active' : 'archived', $equipment->version, null);
    }
}
