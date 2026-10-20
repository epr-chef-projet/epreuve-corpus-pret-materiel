<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\SaveEquipment;

use App\Modules\Catalog\Equipment\Errors\ContentConflict;
use App\Modules\Catalog\Equipment\Errors\VersionExhausted;
use App\Modules\Catalog\Equipment\Models\Equipment;
use InvalidArgumentException;

final class EquipmentTextService
{
    public function validate(EquipmentInput $input): void
    {
        foreach ([$input->name, $input->category, $input->description] as $value) {
            if ($value !== null && (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0") || EquipmentInputFactory::normalize($value) !== $value)) {
                throw new InvalidArgumentException('Equipment text must be valid normalized UTF-8.');
            }
        }
        if ($input->name === '' || $input->category === '' || mb_strlen($input->name) > 120 || mb_strlen($input->category) > 80 || ($input->description !== null && (mb_strlen($input->description) > 2000 || $input->description === '')) || EquipmentInputFactory::normalize($input->name) !== $input->name || EquipmentInputFactory::normalize($input->category) !== $input->category) {
            throw new InvalidArgumentException('Equipment text must be normalized and bounded.');
        }
    }

    public function revise(Equipment $equipment, EquipmentInput $input, int $expectedVersion): void
    {
        $this->validate($input);
        if ($equipment->version !== $expectedVersion) {
            throw new ContentConflict('Equipment was changed since the form was read.');
        }
        if ($equipment->version === 2147483647) {
            throw new VersionExhausted('Equipment version is exhausted.');
        }
        $equipment->name = $input->name;
        $equipment->category = $input->category;
        $equipment->description = $input->description;
        $equipment->version++;
    }
}
