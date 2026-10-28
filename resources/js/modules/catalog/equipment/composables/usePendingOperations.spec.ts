import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { defineComponent, effectScope, ref, type EffectScope } from 'vue';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { createPinia, setActivePinia } from 'pinia';
import { useAccessStore } from '@/modules/identity-access/public/access';
import {
    usePendingOperations,
    installCatalogOperationCleanup,
} from './usePendingOperations';
import { useEquipmentForm } from './useEquipmentForm';
import { catalogMessages } from '../../i18n/messages';
vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/vue3')>();
    return { ...original, usePage: () => ({ props: {} }) };
});
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

it('persists a sending proof without presenting it as unknown to another consumer', async () => {
    const pending = await setup();
    const key = crypto.randomUUID();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );
    const another = scope.run(() => usePendingOperations())!;
    await vi.waitFor(() => expect(another.ready.value).toBe(true));
    expect(another.proofs.value).toHaveLength(1);
    expect(another.unresolved.value).toHaveLength(0);
    expect(sessionStorage.getItem('epr.pendingCatalogOperations')).toContain(
        key,
    );
    pending.endSending(key);
    expect(another.unresolved.value.map((proof) => proof.operationKey)).toEqual(
        [key],
    );
    pending.markSending(key);
    expect(another.unresolved.value).toHaveLength(0);
    pending.reject(key);
    expect(another.proofs.value).toHaveLength(0);
});

it('releases an unfinished sending proof after its form has unmounted', async () => {
    const pending = await setup();
    const key = crypto.randomUUID();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );
    const next = effectScope();
    try {
        scope.stop();
        const another = next.run(() => usePendingOperations())!;
        await vi.waitFor(() => expect(another.ready.value).toBe(true));
        expect(another.unresolved.value).toHaveLength(0);
        pending.endSending(key);
        expect(another.unresolved.value).toHaveLength(1);
    } finally {
        next.stop();
    }
});

it('does not resurrect a confirmed operation when its old request finishes later', async () => {
    const pending = await setup();
    const key = crypto.randomUUID();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );
    pending.finish({
        operationKey: key,
        operationType: 'catalog.equipment.create',
        resourceType: 'equipment',
        resourceId: crypto.randomUUID(),
        resultVersion: 1,
        recordedAt: '2026-10-20T12:00:00.000Z',
        replayed: false,
    });
    pending.endSending(key);
    expect(pending.proofs.value).toEqual([]);
    expect(pending.unresolved.value).toEqual([]);
    expect(sessionStorage.getItem('epr.pendingCatalogOperations')).toBe('[]');
});

it('ignores a former generation finishing after another account starts an operation', async () => {
    const pending = await setup();
    const key = crypto.randomUUID();
    const access = useAccessStore();
    const context = access.context!;
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );
    access.invalidate();
    access.accept({
        ...context,
        authGeneration: 2,
        broadcastToken: 'b'.repeat(64),
    });
    const another = scope.run(() => usePendingOperations())!;
    await vi.waitFor(() => expect(another.ready.value).toBe(true));
    await another.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );
    pending.endSending(key);
    expect(another.unresolved.value).toEqual([]);
    expect(another.proofs.value).toHaveLength(1);
    another.endSending(key);
    expect(another.unresolved.value).toHaveLength(1);
});

it('keeps a lost creation blocked until a matching terminal closure is received', async () => {
    const pending = await setup();
    const key = crypto.randomUUID();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );
    pending.endSending(key);
    const closure = {
        operationKey: key,
        operationType: 'catalog.equipment.create',
        closedAt: '2026-10-26T12:00:00.000Z',
    };

    for (const value of [
        null,
        { status: 'notEstablished', receipt: null },
        { ...closure, operationKey: crypto.randomUUID() },
        { ...closure, operationType: 'catalog.equipment.archive' },
        { ...closure, closedAt: 'not an instant' },
        { ...closure, resourceId: crypto.randomUUID() },
    ]) {
        expect(pending.finishClosed(value)).toBe(false);
        expect(pending.unresolved.value).toHaveLength(1);
    }

    expect(pending.finishClosed(closure)).toBe(true);
    pending.endSending(key);
    expect(pending.proofs.value).toEqual([]);
    expect(sessionStorage.getItem('epr.pendingCatalogOperations')).toBe('[]');
});

it('removes only the closed creation while another operation still needs checking', async () => {
    const pending = await setup();
    const creation = crypto.randomUUID();
    const edit = crypto.randomUUID();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: creation },
        null,
        null,
    );
    await pending.begin(
        'catalog.equipment.update_text',
        { operationKey: edit },
        crypto.randomUUID(),
        2,
    );
    pending.endSending(creation);
    pending.endSending(edit);

    pending.finishClosed({
        operationKey: creation,
        operationType: 'catalog.equipment.create',
        closedAt: '2026-10-26T12:00:00.000Z',
    });

    expect(pending.unresolved.value.map((proof) => proof.operationKey)).toEqual(
        [edit],
    );
    expect(
        sessionStorage.getItem('epr.pendingCatalogOperations'),
    ).not.toContain(creation);
});

it('ignores a former access generation receiving a closure for a newly reused key', async () => {
    const pending = await setup();
    const access = useAccessStore();
    const context = access.context!;
    const key = crypto.randomUUID();
    await pending.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );
    access.invalidate();
    access.accept({
        ...context,
        authGeneration: 2,
        broadcastToken: 'b'.repeat(64),
    });
    const another = scope.run(() => usePendingOperations())!;
    await vi.waitFor(() => expect(another.ready.value).toBe(true));
    await another.begin(
        'catalog.equipment.create',
        { operationKey: key },
        null,
        null,
    );

    expect(
        pending.finishClosed({
            operationKey: key,
            operationType: 'catalog.equipment.create',
            closedAt: '2026-10-26T12:00:00.000Z',
        }),
    ).toBe(false);

    expect(another.proofs.value).toHaveLength(1);
});

it('releases the matching in-memory creation after a closure without changing a later draft', async () => {
    scope.run(() => installCatalogOperationCleanup());
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    let form!: ReturnType<typeof useEquipmentForm>;
    const wrapper = mount(
        defineComponent({
            setup() {
                form = useEquipmentForm(ref(undefined), ref(null));
                return () => null;
            },
        }),
        {
            global: {
                plugins: [
                    createI18n({
                        legacy: false,
                        locale: 'fr',
                        messages: catalogMessages,
                    }),
                ],
            },
        },
    );
    try {
        await vi.waitFor(() => expect(form.pending.ready.value).toBe(true));
        form.form.name = 'Original equipment';
        form.form.category = 'Original category';
        await form.submit();
        const key = form.pending.proofs.value[0].operationKey;
        form.pending.endSending(key);
        form.absent(key);
        form.form.name = 'New equipment draft';
        form.form.category = 'New category';
        form.form.description = 'A newer description';
        form.form.totalQuantity = '4';

        form.closed(crypto.randomUUID());
        expect(form.waiting.value).toBe(true);
        expect(form.resumable.value).toBe(true);
        form.pending.finishClosed({
            operationKey: key,
            operationType: 'catalog.equipment.create',
            closedAt: '2026-10-26T12:00:00.000Z',
        });
        form.closed(key);

        expect(form.waiting.value).toBe(false);
        expect(form.resumable.value).toBe(false);
        expect(form.pendingForTarget.value).toBe(false);
        expect(form.form.data()).toMatchObject({
            name: 'New equipment draft',
            category: 'New category',
            description: 'A newer description',
            totalQuantity: '4',
        });
        expect(post).toHaveBeenCalledTimes(1);
        await form.submit();
        expect(post).toHaveBeenCalledTimes(2);
        expect(form.pending.proofs.value[0].operationKey).not.toBe(key);
        expect(post.mock.calls[1][1]).toMatchObject({
            name: 'New equipment draft',
            description: 'A newer description',
            totalQuantity: '4',
        });
    } finally {
        wrapper.unmount();
    }
});
