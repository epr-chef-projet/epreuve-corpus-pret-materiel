<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\SaveEquipment;

use App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState\ChangeEquipmentStateAction;
use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\EditEquipmentText\EditEquipmentTextAction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EquipmentInputFactory
{
    public const UUID = 'regex:/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D';

    public static function normalize(mixed $value): mixed
    {
        return is_string($value) ? (preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $value) ?? $value) : $value;
    }

    public static function integer(mixed $value): mixed
    {
        $value = self::normalize($value);
        if (is_string($value) && preg_match('/\A[0-9]+\z/D', $value) === 1) {
            $digits = ltrim($value, '0');
            $digits = $digits === '' ? '0' : $digits;
            if (strlen($digits) < strlen((string) PHP_INT_MAX) || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) <= 0)) {
                return (int) $digits;
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $data
     * @return array{input: EquipmentInput, operationKey: string, totalQuantity: int}
     */
    public function create(array $data): array
    {
        $valid = $this->validate($data, ['totalQuantity' => ['required', 'integer:strict', 'between:0,999999']]);

        return ['input' => new EquipmentInput($valid['name'], $valid['category'], $valid['description']), 'operationKey' => $valid['operationKey'], 'totalQuantity' => $valid['totalQuantity']];
    }

    /** @param array<string, mixed> $data
     * @return array{input: EquipmentInput, operationKey: string, expectedVersion: int}
     */
    public function edit(array $data): array
    {
        $valid = $this->validate($data, ['expectedVersion' => ['required', 'integer:strict', 'between:1,2147483647']]);

        return ['input' => new EquipmentInput($valid['name'], $valid['category'], $valid['description']), 'operationKey' => $valid['operationKey'], 'expectedVersion' => $valid['expectedVersion']];
    }

    public function identifier(string $id): string
    {
        Validator::make(['equipmentId' => $id], ['equipmentId' => ['required', 'uuid', self::UUID]])->validate();

        return $id;
    }

    /** @param array<string, mixed> $data
     * @return array{operationKey: string, expectedVersion: int}
     */
    public function state(array $data): array
    {
        if (array_diff(array_keys($data), ['expectedVersion', 'operationKey', '_token']) !== []) {
            throw ValidationException::withMessages(['catalog' => __('catalog.validation.unknown_fields')]);
        }
        $data['expectedVersion'] = self::integer($data['expectedVersion'] ?? null);
        $valid = Validator::make($data, ['expectedVersion' => ['required', 'integer:strict', 'between:1,2147483647'], 'operationKey' => ['required', 'uuid', self::UUID]])->validate();

        return ['operationKey' => $valid['operationKey'], 'expectedVersion' => $valid['expectedVersion']];
    }

    /** @param array<string, mixed> $data
     * @return array{operationType: string, operationKey: string}
     */
    public function operation(array $data): array
    {
        if (array_diff(array_keys($data), ['operationType', 'operationKey']) !== []) {
            throw ValidationException::withMessages(['catalog' => __('catalog.validation.unknown_fields')]);
        }
        $valid = Validator::make($data, ['operationType' => ['required', 'in:'.CreateEquipmentAction::OPERATION.','.EditEquipmentTextAction::OPERATION.','.ChangeEquipmentStateAction::ARCHIVE.','.ChangeEquipmentStateAction::REACTIVATE], 'operationKey' => ['required', 'uuid', self::UUID]])->validate();

        return ['operationType' => $valid['operationType'], 'operationKey' => $valid['operationKey']];
    }

    /** @param array<string, mixed> $data
     * @return array{operationType: string, operationKey: string}
     */
    public function closeOperation(array $data): array
    {
        if (array_diff(array_keys($data), ['operationType', 'operationKey']) !== []) {
            throw ValidationException::withMessages(['catalog' => __('catalog.validation.unknown_fields')]);
        }
        $valid = Validator::make($data, ['operationType' => ['required', 'in:'.CreateEquipmentAction::OPERATION], 'operationKey' => ['required', 'uuid', self::UUID]])->validate();

        return ['operationType' => $valid['operationType'], 'operationKey' => $valid['operationKey']];
    }

    /** @param array<string, mixed> $data
     * @param  array<string, list<string>>  $extra
     * @return array<string, mixed>
     */
    private function validate(array $data, array $extra): array
    {
        $rules = ['name' => ['required', 'string', 'max:120', 'regex:/\A[^\x00]*\z/us'], 'category' => ['required', 'string', 'max:80', 'regex:/\A[^\x00]*\z/us'], 'description' => ['nullable', 'string', 'max:2000', 'regex:/\A[^\x00]*\z/us'], 'operationKey' => ['required', 'uuid', self::UUID], ...$extra];
        if (array_diff(array_keys($data), [...array_keys($rules), '_token']) !== []) {
            throw ValidationException::withMessages(['catalog' => __('catalog.validation.unknown_fields')]);
        }
        foreach (['name', 'category', 'description'] as $key) {
            $data[$key] = self::normalize($data[$key] ?? null);
        }
        if ($data['description'] === '') {
            $data['description'] = null;
        }
        foreach (array_keys($extra) as $key) {
            $data[$key] = self::integer($data[$key] ?? null);
        }

        return Validator::make($data, $rules)->validate();
    }
}
