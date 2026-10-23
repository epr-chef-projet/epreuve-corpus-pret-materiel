<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\Models;

use App\Modules\Catalog\Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $workshop_id
 * @property string $name
 * @property string|null $description
 * @property string $category
 * @property int $total_quantity
 * @property bool $is_active
 * @property int $version
 */
final class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'catalog_equipment';

    protected $connection = 'pgsql';

    protected $guarded = ['*'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['total_quantity' => 'integer', 'version' => 'integer', 'is_active' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): EquipmentFactory
    {
        return EquipmentFactory::new();
    }
}
