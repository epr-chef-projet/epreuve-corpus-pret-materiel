import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { defineComponent, ref } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import type Echo from 'laravel-echo';
import {
    useAccessStore,
    sessionSubscriptionsKey,
    installSessionTransport,
} from '@/modules/identity-access/public/access';
import { useCatalogProjection } from './useCatalogProjection';
let wrapper: VueWrapper | undefined;
let disposeTransport = () => {};
beforeEach(() => {
    vi.useFakeTimers();
    setActivePinia(createPinia());
});
afterEach(() => {
    wrapper?.unmount();
    disposeTransport();
    wrapper = undefined;
    vi.useRealTimers();
});
function fixture() {
    const access = useAccessStore();
    access.accept({
        user: {
            id: '019a0000-0000-7000-8000-000000000001',
            login: 'synthetic',
            displayName: 'Synthetic',
            workshopId: '019a0000-0000-7000-8000-000000000002',
            workshopName: 'Synthetic',
            role: 'manager',
        },
        broadcastToken: 'a'.repeat(64),
        authGeneration: 1,
        expiresAt: '2099-01-01T00:00:00.000Z',
        locale: 'fr',
    });
    let emit: (value: unknown) => void = () => {};
    let confirm = () => {};
    const channel = {
        listen: (_name: string, handler: typeof emit) => {
            emit = handler;
            return channel;
        },
        subscribed: (handler: typeof confirm) => {
            confirm = handler;
            return channel;
        },
        error: () => channel,
    };
    const transport = installSessionTransport(
        {
            private: () => channel,
            leave: vi.fn(),
            connector: {
                pusher: {
                    connection: { bind: vi.fn(), unbind: vi.fn() },
                    connect: vi.fn(),
                    disconnect: vi.fn(),
                },
            },
        } as unknown as Echo<'reverb'>,
        access,
    );
    const subscriptions = transport.subscriptions;
    disposeTransport = () => transport.dispose();
    const filter = ref('first');
    const replies: ((value: string) => void)[] = [];
    const failures: ((error: Error) => void)[] = [];
    const read = vi.fn((signal: AbortSignal) => {
        expect(signal).toBeInstanceOf(AbortSignal);
        return new Promise<string>((resolve, reject) => {
            replies.push(resolve);
            failures.push(reject);
        });
    });
    let projection!: ReturnType<typeof useCatalogProjection<string>>;
    wrapper = mount(
        defineComponent({
            setup() {
                projection = useCatalogProjection(
                    ref('initial'),
                    () => filter.value,
                    read,
                );
                return () => null;
            },
        }),
        {
            global: {
                provide: { [sessionSubscriptionsKey as symbol]: subscriptions },
            },
        },
    );
    const invalidate = () =>
        emit({
            eventId: '019a0000-0000-7000-8000-000000000003',
            contractVersion: 1,
            authGeneration: 1,
            type: 'catalog.invalidated',
            payload: { projection: 'catalogManagement' },
        });
    return {
        access,
        filter,
        read,
        replies,
        failures,
        invalidate,
        confirm: () => confirm(),
        get projection() {
            return projection;
        },
    };
}
it('rejects a read crossed by a buffered invalidation then installs a fresh read', async () => {
    const state = fixture();
    state.confirm();
    await vi.advanceTimersByTimeAsync(0);
    state.invalidate();
    state.replies[0]('outdated');
    await vi.advanceTimersByTimeAsync(100);
    expect(state.projection.data.value).toBe('initial');
    expect(state.read).toHaveBeenCalledTimes(2);
    state.replies[1]('current');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('current');
    expect(state.projection.stale.value).toBe(false);
});
it('does not install a late response for the previous filter', async () => {
    const state = fixture();
    await vi.advanceTimersByTimeAsync(0);
    state.filter.value = 'second';
    await vi.advanceTimersByTimeAsync(0);
    expect(state.read.mock.calls[0][0].aborted).toBe(true);
    state.replies[0]('wrong filter');
    await vi.advanceTimersByTimeAsync(100);
    expect(state.projection.data.value).toBe('initial');
    state.replies[1]('second filter result');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('second filter result');
});
it('recovers without a notification within the visible polling window', async () => {
    const state = fixture();
    await vi.advanceTimersByTimeAsync(0);
    state.replies[0]('first');
    await vi.advanceTimersByTimeAsync(15_000);
    expect(state.read).toHaveBeenCalledTimes(2);
    expect(state.projection.verifying.value).toBe(false);
    expect(state.projection.manualRefreshing.value).toBe(false);
    state.replies[1]('last signal lost');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('last signal lost');
});
it('keeps an initial recovery distinct from a genuinely expired projection', async () => {
    const state = fixture();
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.verifying.value).toBe(true);
    expect(state.projection.expired.value).toBe(false);

    await vi.advanceTimersByTimeAsync(20_000);
    expect(state.projection.data.value).toBe('initial');
    expect(state.projection.expired.value).toBe(true);
    expect(state.projection.verifying.value).toBe(false);

    state.replies[0]('verified');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('verified');
    expect(state.projection.expired.value).toBe(false);
});
it('keeps last known information and warns when a periodic recovery exceeds freshness', async () => {
    const state = fixture();
    await vi.advanceTimersByTimeAsync(0);
    state.replies[0]('known');
    await vi.advanceTimersByTimeAsync(20_000);
    expect(state.projection.busy.value).toBe(true);
    expect(state.projection.data.value).toBe('known');
    expect(state.projection.expired.value).toBe(true);
    expect(state.projection.manualRefreshing.value).toBe(false);

    state.replies[1]('recovered');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('recovered');
    expect(state.projection.expired.value).toBe(false);
});
it('tracks a requested refresh through an older read without presenting automatic reads as user actions', async () => {
    const state = fixture();
    await vi.advanceTimersByTimeAsync(0);
    state.projection.refresh();
    expect(state.projection.manualRefreshing.value).toBe(true);
    state.replies[0]('before request');
    await vi.advanceTimersByTimeAsync(100);
    expect(state.projection.data.value).toBe('initial');
    expect(state.projection.manualRefreshing.value).toBe(true);

    state.replies[1]('requested');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('requested');
    expect(state.projection.manualRefreshing.value).toBe(false);
});
it('ends a failed manual refresh and discards its late response when access ends', async () => {
    const state = fixture();
    await vi.advanceTimersByTimeAsync(0);
    state.replies[0]('known');
    await vi.advanceTimersByTimeAsync(0);
    state.projection.refresh();
    await vi.advanceTimersByTimeAsync(0);
    state.failures[1](new Error('unreachable'));
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.failed.value).toBe(true);
    expect(state.projection.data.value).toBe('known');
    expect(state.projection.manualRefreshing.value).toBe(false);

    state.projection.refresh();
    await vi.advanceTimersByTimeAsync(100);
    state.access.invalidate();
    expect(state.projection.manualRefreshing.value).toBe(false);
    state.replies[2]('old context');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('known');
});
it('starts an explicit retry immediately instead of waiting for the automatic backoff', async () => {
    const state = fixture();
    await vi.advanceTimersByTimeAsync(0);
    state.replies[0]('known');
    await vi.advanceTimersByTimeAsync(0);
    state.projection.refresh();
    await vi.advanceTimersByTimeAsync(0);
    for (const index of [1, 2, 3]) {
        state.failures[index](new Error('unreachable'));
        await vi.advanceTimersByTimeAsync(index < 3 ? 100 : 0);
    }
    expect(state.read).toHaveBeenCalledTimes(4);

    state.projection.refresh();
    await vi.advanceTimersByTimeAsync(0);
    expect(state.read).toHaveBeenCalledTimes(5);
    expect(state.projection.manualRefreshing.value).toBe(true);
    state.replies[4]('manual retry');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('manual retry');
    expect(state.projection.failed.value).toBe(false);
    expect(state.projection.manualRefreshing.value).toBe(false);
});
it('refuses an overflowed snapshot and discards a response after access ends', async () => {
    const state = fixture();
    state.confirm();
    await vi.advanceTimersByTimeAsync(0);
    for (let i = 0; i < 101; i++) state.invalidate();
    state.replies[0]('overflowed');
    await vi.advanceTimersByTimeAsync(100);
    expect(state.projection.data.value).toBe('initial');
    state.access.invalidate();
    state.replies[1]('former private context');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('initial');
});

it('keeps a newer equipment version when an older Inertia page and read arrive late', async () => {
    const initial = ref({ equipmentVersion: 1, name: 'first' });
    const replies: ((value: typeof initial.value) => void)[] = [];
    let projection!: ReturnType<
        typeof useCatalogProjection<typeof initial.value>
    >;
    wrapper = mount(
        defineComponent({
            setup() {
                projection = useCatalogProjection(
                    initial,
                    () => 'same-equipment',
                    () => new Promise((resolve) => replies.push(resolve)),
                    (incoming, current) =>
                        incoming.equipmentVersion >= current.equipmentVersion,
                );
                return () => null;
            },
        }),
    );
    await vi.advanceTimersByTimeAsync(0);
    replies[0]({ equipmentVersion: 3, name: 'newest' });
    await vi.advanceTimersByTimeAsync(0);
    initial.value = { equipmentVersion: 2, name: 'older redirect' };
    await vi.advanceTimersByTimeAsync(0);
    expect(projection.data.value).toEqual({
        equipmentVersion: 3,
        name: 'newest',
    });
    replies[1]({ equipmentVersion: 2, name: 'older read' });
    await vi.advanceTimersByTimeAsync(100);
    expect(projection.data.value.equipmentVersion).toBe(3);
    replies[2]({ equipmentVersion: 4, name: 'fresh read' });
    await vi.advanceTimersByTimeAsync(0);
    expect(projection.data.value.equipmentVersion).toBe(4);
});
