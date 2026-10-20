import type {
    ManagementPageData as EquipmentPage,
    EquipmentSummaryData,
} from '@/types/generated/types/App/Modules/Catalog/Equipment/UseCases/ReadEquipment';
import type { EquipmentData } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type {
    CommandReceipt,
    OperationResultData,
} from '@/types/generated/types/App/Infrastructure/Operations';

export function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
export const uuid = (value: unknown): value is string =>
    typeof value === 'string' &&
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(
        value,
    );
const integer = (value: unknown, min: number, max: number): value is number =>
    Number.isSafeInteger(value) && Number(value) >= min && Number(value) <= max;
const text = (value: unknown, max: number): value is string =>
    typeof value === 'string' && [...value].length <= max;
const instant = (value: unknown): value is string =>
    typeof value === 'string' &&
    /^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/.test(value) &&
    Number.isFinite(Date.parse(value)) &&
    new Date(value).toISOString() === value;
export const operationTypes = [
    'catalog.equipment.create',
    'catalog.equipment.update_text',
] as const;
export type OperationType = (typeof operationTypes)[number];
function equipmentFields(value: Record<string, unknown>): boolean {
    return (
        uuid(value.equipmentId) &&
        text(value.name, 120) &&
        value.name.length > 0 &&
        text(value.category, 80) &&
        value.category.length > 0 &&
        integer(value.totalQuantity, 0, 999999) &&
        integer(value.equipmentVersion, 1, 2147483647) &&
        ['active', 'archived'].includes(String(value.status)) &&
        value.image === null
    );
}
export function isEquipment(value: unknown): value is EquipmentData {
    return (
        record(value) &&
        Object.keys(value).length === 8 &&
        equipmentFields(value) &&
        (value.description === null || text(value.description, 2000))
    );
}
export function isEquipmentSummary(
    value: unknown,
): value is EquipmentSummaryData {
    return (
        record(value) &&
        Object.keys(value).length === 7 &&
        equipmentFields(value)
    );
}
export function isEquipmentPage(value: unknown): value is EquipmentPage {
    return (
        record(value) &&
        Object.keys(value).length === 5 &&
        Array.isArray(value.items) &&
        value.items.length <= 100 &&
        value.items.every(isEquipmentSummary) &&
        new Set(
            value.items.map((item: EquipmentSummaryData) => item.equipmentId),
        ).size === value.items.length &&
        integer(value.page, 1, Number.MAX_SAFE_INTEGER) &&
        integer(value.perPage, 1, 100) &&
        value.items.length <= value.perPage &&
        typeof value.hasMore === 'boolean' &&
        instant(value.observedAt)
    );
}
export function isReceipt(value: unknown): value is CommandReceipt {
    return (
        record(value) &&
        Object.keys(value).length === 7 &&
        uuid(value.operationKey) &&
        operationTypes.some((type) => type === value.operationType) &&
        value.resourceType === 'equipment' &&
        uuid(value.resourceId) &&
        integer(value.resultVersion, 1, 2147483647) &&
        instant(value.recordedAt) &&
        typeof value.replayed === 'boolean'
    );
}
export function isOperationResult(
    value: unknown,
): value is OperationResultData {
    return (
        record(value) &&
        Object.keys(value).length === 2 &&
        ((value.status === 'notEstablished' && value.receipt === null) ||
            (value.status === 'applied' && isReceipt(value.receipt)))
    );
}
export function isCatalogInvalidation(payload: unknown): boolean {
    return (
        record(payload) &&
        Object.keys(payload).length === 1 &&
        ['catalog', 'catalogManagement'].includes(String(payload.projection))
    );
}
