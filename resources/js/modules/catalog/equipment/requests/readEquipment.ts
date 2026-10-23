import type { ManagementPageData as EquipmentPage } from '@/types/generated/types/App/Modules/Catalog/Equipment/UseCases/ReadEquipment';
import { http } from '@inertiajs/core';
import { page, detail, operation } from '@/routes/catalog';
import { withRecoverySlot } from '@/shared/network/recoveryBudget';
import type {
    EquipmentData,
    EquipmentQuery,
} from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type { OperationResultData } from '@/types/generated/types/App/Infrastructure/Operations';
import {
    isEquipment,
    isEquipmentPage,
    isOperationResult,
    type OperationType,
} from '../contracts/decoders';

export class CatalogReadError extends Error {
    constructor(public readonly issue: unknown = null) {
        super('Catalogue recovery failed');
    }
}
async function read<T>(
    url: string,
    accept: (value: unknown) => value is T,
    signal: AbortSignal,
): Promise<T> {
    return withRecoverySlot(async () => {
        const response = await http.getClient().request({
            method: 'get',
            url,
            headers: { Accept: 'application/json' },
            signal: AbortSignal.any([signal, AbortSignal.timeout(5000)]),
        });
        let data: unknown;
        try {
            data = JSON.parse(response.data);
        } catch {
            throw new CatalogReadError();
        }
        if (response.status !== 200 || !accept(data))
            throw new CatalogReadError(data);
        return data;
    }, signal);
}
export const readEquipmentPage = (
    query: EquipmentQuery,
    signal: AbortSignal,
): Promise<EquipmentPage> =>
    read(
        page.url({
            query: {
                search: query.search,
                category: query.category ?? undefined,
                page: query.page,
                perPage: query.perPage,
            },
        }),
        isEquipmentPage,
        signal,
    );
export const readEquipment = (
    id: string,
    signal: AbortSignal,
): Promise<EquipmentData> => read(detail.url(id), isEquipment, signal);
export const readOperation = (
    type: OperationType,
    key: string,
    signal: AbortSignal,
    resourceId: string | null = null,
): Promise<OperationResultData> =>
    read(
        operation.url({ query: { operationType: type, operationKey: key } }),
        (value: unknown): value is OperationResultData =>
            isOperationResult(value) &&
            (value.status === 'notEstablished' ||
                (value.receipt !== null &&
                    value.receipt.operationType === type &&
                    value.receipt.operationKey === key &&
                    (resourceId === null ||
                        value.receipt.resourceId === resourceId))),
        signal,
    );
