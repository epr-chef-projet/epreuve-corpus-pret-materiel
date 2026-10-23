<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\SaveEquipment;

final readonly class EquipmentInput
{
    public function __construct(public string $name, public string $category, public ?string $description) {}
}
