import { describe, expect, it, vi } from 'vitest';
import { http, HttpResponseError } from '@inertiajs/core';
import { closeOperation, readOperation } from '../requests/readEquipment';
import {
    isEquipment,
    isEquipmentPage,
    isReceipt,
    isOperationResult,
    isCatalogInvalidation,
} from './decoders';
const item = {
    equipmentId: '019a0000-0000-7000-8000-000000000001',
    name: 'Text',
    description: null,
    category: 'Tools',
    totalQuantity: 0,
    status: 'active',
    equipmentVersion: 1,
    image: null,
};
const receipt = {
    operationKey: item.equipmentId,
    operationType: 'catalog.equipment.create',
    resourceType: 'equipment',
    resourceId: item.equipmentId,
    resultVersion: 1,
    recordedAt: '2026-10-20T18:00:00.000Z',
    replayed: false,
};
describe('catalogue runtime contracts', () => {
    it('accepts the selected equipment shape and rejects extra data, invalid quantities and versions', () => {
        expect(isEquipment(item)).toBe(true);
        for (const value of [
            { ...item, totalQuantity: -1 },
            { ...item, equipmentVersion: 0 },
            { ...item, equipmentVersion: 2147483648 },
            { ...item, workshopId: item.equipmentId },
            { ...item, image: '/public.jpg' },
        ])
            expect(isEquipment(value)).toBe(false);
    });
    it('rejects duplicate rows, an oversized page and a noncanonical observation instant', () => {
        const page = {
            items: [
                {
                    equipmentId: item.equipmentId,
                    name: item.name,
                    category: item.category,
                    totalQuantity: item.totalQuantity,
                    status: item.status,
                    equipmentVersion: item.equipmentVersion,
                    image: null,
                },
            ],
            page: 1,
            perPage: 25,
            hasMore: false,
            observedAt: receipt.recordedAt,
        };
        expect(isEquipmentPage(page)).toBe(true);
        expect(
            isEquipmentPage({ ...page, items: [page.items[0], page.items[0]] }),
        ).toBe(false);
        expect(isEquipmentPage({ ...page, perPage: 101 })).toBe(false);
        expect(
            isEquipmentPage({ ...page, observedAt: '2026-10-20T18:00:00Z' }),
        ).toBe(false);
    });
    it('distinguishes an acquired receipt from an absence that does not prove rollback', () => {
        expect(isReceipt(receipt)).toBe(true);
        expect(isOperationResult({ status: 'applied', receipt })).toBe(true);
        expect(
            isOperationResult({ status: 'notEstablished', receipt: null }),
        ).toBe(true);
        expect(isOperationResult({ status: 'notEstablished', receipt })).toBe(
            false,
        );
        expect(
            isReceipt({ ...receipt, operationType: 'arbitrary.class' }),
        ).toBe(false);
    });
    it.each(['catalog.equipment.archive', 'catalog.equipment.reactivate'])(
        'accepts only the selected receipt shape for %s',
        (operationType) => {
            expect(isReceipt({ ...receipt, operationType })).toBe(true);
            expect(
                isOperationResult({
                    status: 'applied',
                    receipt: { ...receipt, operationType },
                }),
            ).toBe(true);
            expect(
                isReceipt({ ...receipt, operationType, name: 'Private text' }),
            ).toBe(false);
        },
    );
    it('accepts a terminal creation closure without manufacturing an acquired receipt', () => {
        const closure = {
            operationKey: receipt.operationKey,
            operationType: 'catalog.equipment.create',
            closedAt: receipt.recordedAt,
        };
        const closed = { status: 'closed', receipt: null, closure };

        expect(isOperationResult(closed)).toBe(true);
        expect(isReceipt(closure)).toBe(false);
        for (const value of [
            { ...closed, receipt },
            { ...closed, closure: null },
            {
                ...closed,
                closure: {
                    ...closure,
                    operationType: 'catalog.equipment.archive',
                },
            },
            { ...closed, closure: { ...closure, operationKey: 'invalid' } },
            {
                ...closed,
                closure: { ...closure, closedAt: '2026-10-20T18:00:00Z' },
            },
            {
                ...closed,
                closure: { ...closure, resourceId: receipt.resourceId },
            },
            { ...closed, privateText: 'hidden' },
            { status: 'applied', receipt, closure },
            { status: 'notEstablished', receipt: null, closure },
        ])
            expect(isOperationResult(value)).toBe(false);
    });
    it('posts only the explicit closure identity and rejects a different terminal operation', async () => {
        const client = http.getClient();
        const closed = {
            status: 'closed',
            receipt: null,
            closure: {
                operationKey: receipt.operationKey,
                operationType: 'catalog.equipment.create',
                closedAt: receipt.recordedAt,
            },
        };
        const request = vi.fn().mockResolvedValue({
            status: 200,
            headers: {},
            data: JSON.stringify(closed),
        });
        http.setClient({ request });
        try {
            const signal = new AbortController().signal;

            await expect(
                closeOperation(receipt.operationKey, signal),
            ).resolves.toEqual(closed);
            expect(request).toHaveBeenCalledTimes(1);
            expect(request.mock.calls[0][0]).toMatchObject({
                method: 'post',
                url: '/gestion/materiels/operations/cloture',
                data: {
                    operationType: 'catalog.equipment.create',
                    operationKey: receipt.operationKey,
                },
            });
            request.mockResolvedValueOnce({
                status: 200,
                headers: {},
                data: JSON.stringify({
                    ...closed,
                    closure: {
                        ...closed.closure,
                        operationKey: '019a0000-0000-7000-8000-000000000009',
                    },
                }),
            });
            await expect(
                closeOperation(receipt.operationKey, signal),
            ).rejects.toThrow();
            request.mockResolvedValueOnce({
                status: 200,
                headers: {},
                data: JSON.stringify({
                    status: 'applied',
                    receipt: {
                        ...receipt,
                        operationType: 'catalog.equipment.archive',
                    },
                }),
            });
            await expect(
                closeOperation(receipt.operationKey, signal),
            ).rejects.toThrow();
        } finally {
            http.setClient(client);
        }
    });
    it('reads a closure only for the requested creation and never treats it as a known-resource receipt', async () => {
        const client = http.getClient();
        const closed = {
            status: 'closed',
            receipt: null,
            closure: {
                operationKey: receipt.operationKey,
                operationType: 'catalog.equipment.create',
                closedAt: receipt.recordedAt,
            },
        };
        http.setClient({
            request: vi.fn().mockResolvedValue({
                status: 200,
                headers: {},
                data: JSON.stringify(closed),
            }),
        });
        try {
            const signal = new AbortController().signal;

            await expect(
                readOperation(
                    'catalog.equipment.create',
                    receipt.operationKey,
                    signal,
                ),
            ).resolves.toEqual(closed);
            await expect(
                readOperation(
                    'catalog.equipment.archive',
                    receipt.operationKey,
                    signal,
                ),
            ).rejects.toThrow();
            await expect(
                readOperation(
                    'catalog.equipment.create',
                    receipt.operationKey,
                    signal,
                    receipt.resourceId,
                ),
            ).rejects.toThrow();
        } finally {
            http.setClient(client);
        }
    });
    it('preserves the server retry delay without replaying a rate-limited closure', async () => {
        const client = http.getClient();
        const request = vi.fn().mockRejectedValue(
            new HttpResponseError('Rate limit', {
                status: 429,
                headers: { 'retry-after': '17' },
                data: '{}',
            }),
        );
        http.setClient({ request });
        try {
            await expect(
                closeOperation(
                    receipt.operationKey,
                    new AbortController().signal,
                ),
            ).rejects.toMatchObject({ retryAfter: 17 });
            expect(request).toHaveBeenCalledTimes(1);
        } finally {
            http.setClient(client);
        }
    });
    it('accepts only the concrete invalidation payload and rejects additional private fields', () => {
        expect(isCatalogInvalidation({ projection: 'catalogManagement' })).toBe(
            true,
        );
        expect(
            isCatalogInvalidation({
                projection: 'catalogManagement',
                privateName: 'hidden',
            }),
        ).toBe(false);
        expect(isCatalogInvalidation({ projection: 'unknown' })).toBe(false);
    });
});
