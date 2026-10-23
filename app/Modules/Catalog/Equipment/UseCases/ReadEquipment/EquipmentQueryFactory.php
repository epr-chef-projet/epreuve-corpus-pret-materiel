<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipment;

use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInputFactory;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\Catalog\PublicApi\EquipmentQuery;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EquipmentQueryFactory
{
    /** @param array<string, mixed> $data */
    public function management(array $data): EquipmentQuery
    {
        if (array_diff(array_keys($data), ['search', 'category', 'page', 'perPage']) !== []) {
            throw ValidationException::withMessages(['catalog' => __('catalog.validation.unknown_fields')]);
        }
        $data = ['search' => EquipmentInputFactory::normalize($data['search'] ?? ''), 'category' => EquipmentInputFactory::normalize($data['category'] ?? null), 'page' => EquipmentInputFactory::integer($data['page'] ?? 1), 'perPage' => EquipmentInputFactory::integer($data['perPage'] ?? 25)];
        if ($data['category'] === '') {
            $data['category'] = null;
        }
        $valid = Validator::make($data, ['search' => ['nullable', 'string', 'max:120', 'regex:/\A[^\x00]*\z/us'], 'category' => ['nullable', 'string', 'max:80', 'regex:/\A[^\x00]*\z/us'], 'page' => ['required', 'integer:strict', 'between:1,'.EquipmentQuery::MAX_PAGE], 'perPage' => ['required', 'integer:strict', 'between:1,100']])->validate();

        return new EquipmentQuery($valid['search'] ?? '', $valid['category'], $valid['page'], $valid['perPage'], CatalogAudience::Management);
    }
}
