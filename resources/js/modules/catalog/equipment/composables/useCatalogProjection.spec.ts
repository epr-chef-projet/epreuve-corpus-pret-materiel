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
    const read = vi.fn((signal: AbortSignal) => {
        expect(signal).toBeInstanceOf(AbortSignal);
        return new Promise<string>((resolve) => replies.push(resolve));
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
    state.replies[1]('last signal lost');
    await vi.advanceTimersByTimeAsync(0);
    expect(state.projection.data.value).toBe('last signal lost');
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
