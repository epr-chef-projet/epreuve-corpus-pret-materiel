import type { ManagementPageData as EquipmentPage } from '@/types/generated/types/App/Modules/Catalog/Equipment/UseCases/ReadEquipment';
import { http, HttpResponseError, type HttpResponse } from '@inertiajs/core';
import { page, detail, operation } from '@/routes/catalog';
import { close } from '@/routes/catalog/operation';
import { withRecoverySlot } from '@/shared/network/recoveryBudget';
import type {
    EquipmentData,
    EquipmentQuery,
} from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type {
    ClosedOperationResultData,
    OperationResultData,
} from '@/types/generated/types/App/Infrastructure/Operations';
import {
    isEquipment,
    isEquipmentPage,
    isOperationResult,
    uuid,
    type OperationType,
} from '../contracts/decoders';

export class CatalogReadError extends Error {
    constructor(
        public readonly issue: unknown = null,
        public readonly retryAfter: number | null = null,
    ) {
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
): Promise<OperationResultData | ClosedOperationResultData> =>
    read(
        operation.url({ query: { operationType: type, operationKey: key } }),
        (
            value: unknown,
        ): value is OperationResultData | ClosedOperationResultData =>
            matchesOperation(value, type, key, resourceId),
        signal,
    );

function matchesOperation(
    value: unknown,
    type: OperationType,
    key: string,
    resourceId: string | null,
): value is OperationResultData | ClosedOperationResultData {
    if (!isOperationResult(value)) return false;
    if (value.status === 'notEstablished') return true;
    if (value.status === 'closed')
        return (
            type === 'catalog.equipment.create' &&
            resourceId === null &&
            value.closure.operationType === type &&
            value.closure.operationKey === key
        );
    return (
        value.receipt !== null &&
        value.receipt.operationType === type &&
        value.receipt.operationKey === key &&
        (resourceId === null || value.receipt.resourceId === resourceId)
    );
}

function limited(response: {
    headers: Record<string, string>;
}): CatalogReadError {
    const delay = response.headers['retry-after'] ?? '';
    const seconds = /^[1-9]\d*$/.test(delay) ? Number(delay) : NaN;
    return new CatalogReadError(
        null,
        Number.isSafeInteger(seconds) && seconds > 0 ? seconds : null,
    );
}

export async function closeOperation(
    key: string,
    signal: AbortSignal,
): Promise<OperationResultData | ClosedOperationResultData> {
    if (!uuid(key)) throw new CatalogReadError();
    let response: HttpResponse;
    try {
        response = await http.getClient().request({
            method: 'post',
            url: close.url(),
            data: {
                operationType: 'catalog.equipment.create',
                operationKey: key,
            },
            headers: { Accept: 'application/json' },
            signal: AbortSignal.any([signal, AbortSignal.timeout(5000)]),
        });
    } catch (error) {
        if (error instanceof HttpResponseError && error.response.status === 429)
            throw limited(error.response);
        throw error;
    }
    if (response.status === 429) throw limited(response);
    let value: unknown;
    try {
        value = JSON.parse(response.data);
    } catch {
        throw new CatalogReadError();
    }
    if (
        response.status !== 200 ||
        !matchesOperation(value, 'catalog.equipment.create', key, null)
    )
        throw new CatalogReadError();
    return value;
}
