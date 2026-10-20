import { describe, expect, it } from 'vitest';
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
