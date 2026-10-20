import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { effectScope, type EffectScope } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { useAccessStore } from '@/modules/identity-access/public/access';
import {
    usePendingOperations,
    installCatalogOperationCleanup,
} from './usePendingOperations';
let scope: EffectScope;
beforeEach(async () => {
    const { webcrypto } = await vi.importActual<{ webcrypto: Crypto }>(
        'node:crypto',
    );
    vi.stubGlobal('crypto', webcrypto);
    sessionStorage.clear();
    setActivePinia(createPinia());
    useAccessStore().accept({
        user: {
            id: '019a0000-0000-7000-8000-000000000001',
            login: 'synthetic',
            displayName: 'Synthetic',
            workshopId: '019a0000-0000-7000-8000-000000000002',
            workshopName: 'Synthetic workshop',
            role: 'manager',
        },
        broadcastToken: 'a'.repeat(64),
        authGeneration: 1,
        expiresAt: '2099-01-01T00:00:00.000Z',
        locale: 'fr',
    });
    scope = effectScope();
});
afterEach(() => {
    useAccessStore().invalidate();
    scope.stop();
    vi.unstubAllGlobals();
});
async function setup() {
    const pending = scope.run(() => {
        installCatalogOperationCleanup();
        return usePendingOperations();
    })!;
    await vi.waitFor(() => expect(pending.ready.value).toBe(true));
    return pending;
}
it('retains only a bounded proof and clears it when the account generation ends', async () => {
    const pending = await setup();
    const operationKey = crypto.randomUUID();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey, name: 'Private form text', totalQuantity: 9999 },
        null,
        null,
    );
    const stored = sessionStorage.getItem('epr.pendingCatalogOperations')!;
    expect(stored).not.toContain('Private form text');
    expect(stored).not.toContain('totalQuantity');
    expect(stored).not.toContain('a'.repeat(64));
    const decoded: unknown = JSON.parse(stored);
    if (
        !Array.isArray(decoded) ||
        !decoded[0] ||
        typeof decoded[0] !== 'object'
    )
        throw new Error('Missing proof');
    expect(Object.keys(decoded[0] as object)).toEqual([
        'scope',
        'operationKey',
        'operationType',
        'resourceId',
        'expectedVersion',
        'contentHash',
    ]);
    useAccessStore().invalidate();
    expect(sessionStorage.getItem('epr.pendingCatalogOperations')).toBeNull();
    expect(pending.proofs.value).toEqual([]);
});
it('enforces twenty proofs even when the twenty-first hash finishes concurrently', async () => {
    const pending = await setup();
    const results = await Promise.allSettled(
        Array.from({ length: 21 }, () =>
            pending.begin(
                'catalog.equipment.create',
                { operationKey: crypto.randomUUID(), name: 'Synthetic' },
                null,
                null,
            ),
        ),
    );
    expect(
        results.filter((result) => result.status === 'fulfilled'),
    ).toHaveLength(20);
    expect(
        results.filter((result) => result.status === 'rejected'),
    ).toHaveLength(1);
    expect(pending.proofs.value).toHaveLength(20);
});
it('keeps the current in-memory proof visible and reports when browser storage is unavailable', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
        throw new Error('Unavailable');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new Error('Unavailable');
    });
    const pending = await setup();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: crypto.randomUUID(), name: 'Private' },
        null,
        null,
    );
    expect(pending.unavailable.value).toBe(true);
    expect(pending.proofs.value).toHaveLength(1);
});

it('does not overwrite an in-memory proof with old storage when only writes fail', async () => {
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new Error('Quota exceeded');
    });
    const pending = await setup();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: crypto.randomUUID(), name: 'Private' },
        null,
        null,
    );
    expect(pending.proofs.value).toHaveLength(1);
    const another = scope.run(() => usePendingOperations())!;
    await vi.waitFor(() => expect(another.ready.value).toBe(true));
    expect(another.proofs.value).toHaveLength(1);
    expect(another.unavailable.value).toBe(true);
});
