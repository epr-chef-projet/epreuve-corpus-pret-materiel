import { createPinia, setActivePinia } from 'pinia';
import type Echo from 'laravel-echo';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    createSessionSubscriptions,
    decodeEnvelope,
} from '@/modules/identity-access/access/notifications/sessionSubscriptions';
import { useAccessStore } from '@/modules/identity-access/access/stores/useAccessStore';
import type { SessionContextData } from '@/types/generated/types/App/Modules/IdentityAccess/Access/Data';

const context: SessionContextData = {
    user: {
        id: '019a0000-0000-7000-8000-000000000001',
        login: 'synthetic',
        displayName: 'Synthetic',
        workshopId: '019a0000-0000-7000-8000-000000000002',
        workshopName: 'Synthetic workshop',
        role: 'borrower',
    },
    broadcastToken: 'a'.repeat(64),
    authGeneration: 1,
    expiresAt: '2026-10-20T20:00:00.000Z',
    locale: 'fr',
};
const envelope = () => ({
    eventId: '019a0000-0000-7000-8000-000000000003',
    contractVersion: 1,
    authGeneration: 1,
    type: 'catalog.invalidated',
    payload: { projection: 'catalog' },
});
function setup() {
    const access = useAccessStore();
    access.accept(context);
    let message: (value: unknown) => void = () => {};
    let subscribed = () => {};
    const channel = {
        listen: vi.fn((_name: string, handler: (value: unknown) => void) => {
            message = handler;
            return channel;
        }),
        subscribed: vi.fn((handler: () => void) => {
            subscribed = handler;
            return channel;
        }),
        error: vi.fn(() => channel),
    };
    const echo = { private: vi.fn(() => channel), leave: vi.fn() };
    const subscriptions = createSessionSubscriptions(
        echo as unknown as Echo<'reverb'>,
        access,
    );
    return {
        access,
        echo,
        subscriptions,
        emit: (value: unknown) => message(value),
        confirm: () => subscribed(),
    };
}
const observer = () => ({
    onSubscribed: vi.fn(),
    onMessage: vi.fn(),
    onStale: vi.fn(),
});
beforeEach(() => setActivePinia(createPinia()));
describe('session subscription ownership', () => {
    it('captures before confirmation and while the HTTP snapshot is in flight for each shared consumer', async () => {
        const fixture = setup();
        const one = observer();
        const first = fixture.subscriptions.acquire(context, one);
        fixture.emit(envelope());
        fixture.confirm();
        expect(one.onMessage).not.toHaveBeenCalled();
        expect(first.activate(() => {})).toBe(true);
        expect(one.onMessage).toHaveBeenCalledOnce();
        const two = observer();
        const second = fixture.subscriptions.acquire(context, two);
        fixture.emit(envelope());
        await Promise.resolve();
        expect(two.onSubscribed).toHaveBeenCalledOnce();
        expect(two.onMessage).not.toHaveBeenCalled();
        expect(second.activate(() => {})).toBe(true);
        expect(two.onMessage).toHaveBeenCalledOnce();
        first.release();
        expect(fixture.echo.leave).not.toHaveBeenCalled();
        fixture.emit(envelope());
        expect(two.onMessage).toHaveBeenCalledTimes(2);
        second.release();
        expect(fixture.echo.leave).toHaveBeenCalledOnce();
        expect(fixture.echo.private).toHaveBeenCalledOnce();
    });
    it('abandons overflowing snapshots and requires a fresh capture after reconnect', () => {
        const fixture = setup();
        const target = observer();
        const consumer = fixture.subscriptions.acquire(context, target);
        fixture.confirm();
        for (let index = 0; index < 101; index++) fixture.emit(envelope());
        const install = vi.fn();
        expect(consumer.activate(install)).toBe(false);
        expect(install).not.toHaveBeenCalled();
        expect(target.onStale).toHaveBeenCalled();
        consumer.capture();
        expect(consumer.activate(install)).toBe(true);
        fixture.subscriptions.degrade();
        expect(consumer.activate(install)).toBe(false);
        fixture.confirm();
        consumer.capture();
        expect(consumer.activate(install)).toBe(true);
        consumer.release();
    });
    it('rejects old generations, incompatible formats, oversized messages and stale handles after A/B/A', () => {
        const fixture = setup();
        const target = observer();
        const consumer = fixture.subscriptions.acquire(context, target);
        fixture.confirm();
        fixture.emit({
            ...envelope(),
            payload: { private: 'x'.repeat(17 * 1024) },
        });
        expect(consumer.activate(() => {})).toBe(false);
        expect(
            decodeEnvelope({ ...envelope(), contractVersion: 2 }, 1),
        ).toBeNull();
        expect(
            decodeEnvelope({ ...envelope(), authGeneration: 2 }, 1),
        ).toBeNull();
        fixture.access.invalidate();
        fixture.access.accept(context);
        fixture.emit(envelope());
        expect(target.onMessage).not.toHaveBeenCalled();
        expect(consumer.activate(() => {})).toBe(false);
        expect(fixture.echo.leave).toHaveBeenCalledOnce();
    });
});
