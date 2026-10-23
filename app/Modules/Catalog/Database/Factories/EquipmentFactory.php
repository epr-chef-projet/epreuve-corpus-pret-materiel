<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Equipment\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Equipment> */
final class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'category' => 'Tools', 'description' => null, 'total_quantity' => 2, 'is_active' => true, 'version' => 1];
    }
}
