import { flushPromises } from '@vue/test-utils';
import {
    http,
    HttpCancelledError,
    HttpNetworkError,
    type HttpResponse,
} from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { installAccessLifecycle } from '@/modules/identity-access/access/lifecycle';
import { useAccessStore } from '@/modules/identity-access/access/stores/useAccessStore';
import { createPresentation } from '@/shared/i18n/presentation';
import { commonMessages } from '@/shared/i18n/messages';
import { readContext } from '@/modules/identity-access/access/requests/readContext';

const originalClient = http.getClient();
const guest = {
    user: null,
    broadcastToken: null,
    authGeneration: null,
    expiresAt: null,
    locale: 'fr',
} as const;
let dispose = () => {};
beforeEach(() => {
    dispose = () => {};
    setActivePinia(createPinia());
    vi.spyOn(router, 'on').mockReturnValue(() => {});
    vi.spyOn(router, 'replace').mockImplementation(() => {});
    vi.spyOn(router, 'cancelAll').mockImplementation(() => {});
    vi.spyOn(router, 'flushAll').mockImplementation(() => {});
    vi.spyOn(router, 'clearHistory').mockImplementation(() => {});
});
afterEach(() => {
    dispose();
    http.setClient(originalClient);
    vi.useRealTimers();
});

function setup() {
    let resolve!: (value: HttpResponse) => void;
    let reject!: (reason: unknown) => void;
    // The delayed client intentionally ignores abort, representing an already engaged response.
    const delayed = new Promise<HttpResponse>((yes, no) => {
        resolve = yes;
        reject = no;
    });
    http.setClient({
        request: (config) =>
            config.url === '/access/context'
                ? Promise.resolve({
                      data: JSON.stringify(guest),
                      status: 200,
                      headers: {},
                  })
                : delayed,
    });
    const presentation = createPresentation(commonMessages);
    const lifecycle = installAccessLifecycle(presentation);
    dispose = () => {
        lifecycle.dispose();
        presentation.dispose();
    };
    return { resolve, reject, lifecycle, access: useAccessStore() };
}
describe('HTTP generation boundary', () => {
    it('closes the current screen when a private response authoritatively rejects the session', async () => {
        const fixture = setup();
        await flushPromises();
        const generation = fixture.access.generation;
        const response = http
            .getClient()
            .request({ url: '/private', method: 'get' });
        const observed =
            expect(response).rejects.toBeInstanceOf(HttpCancelledError);
        fixture.resolve({ data: '{"issue":{}}', status: 401, headers: {} });
        await observed;
        await flushPromises();
        expect(fixture.access.generation).toBeGreaterThan(generation);
        expect(fixture.access.status).toBe('guest');
    });
    it('treats an authoritative authentication refusal as an empty context rather than a network outage', async () => {
        http.setClient({
            request: () =>
                Promise.resolve({
                    status: 401,
                    data: '{"issue":{}}',
                    headers: { 'content-language': 'en' },
                }),
        });
        const result = await readContext(new AbortController().signal);
        expect(result).toEqual({ ...guest, locale: 'en' });
    });
    it('ignores an old successful response even when the same context is active again', async () => {
        const fixture = setup();
        await flushPromises();
        const response = http
            .getClient()
            .request({ url: '/delayed', method: 'get', headers: {} });
        const observed =
            expect(response).rejects.toBeInstanceOf(HttpCancelledError);
        fixture.access.invalidate();
        fixture.access.accept(guest);
        fixture.access.invalidate();
        fixture.access.accept(guest);
        fixture.resolve({
            data: 'old private response',
            status: 200,
            headers: {},
        });
        await observed;
        expect(fixture.access.status).toBe('guest');
        expect(fixture.access.confirming).toBe(false);
    });
    it('ignores old failures and their finalization instead of changing the current connection state', async () => {
        const fixture = setup();
        await flushPromises();
        const response = http
            .getClient()
            .request({ url: '/delayed', method: 'get', headers: {} });
        const observed =
            expect(response).rejects.toBeInstanceOf(HttpCancelledError);
        fixture.access.invalidate();
        fixture.access.accept(guest);
        fixture.reject(new HttpNetworkError('Old network failure'));
        await observed;
        expect(fixture.access.status).toBe('guest');
        expect(fixture.access.stale).toBe(false);
    });
    it('keeps a current HTTP outage distinct from an invalid session', async () => {
        const fixture = setup();
        await flushPromises();
        const response = http
            .getClient()
            .request({ url: '/delayed', method: 'get', headers: {} });
        const observed =
            expect(response).rejects.toBeInstanceOf(HttpNetworkError);
        fixture.reject(new HttpNetworkError('Current network failure'));
        await observed;
        expect(fixture.access.status).toBe('offline');
        expect(fixture.access.context).toEqual(guest);
    });
    it('recovers when the first context read after an online signal still fails without replaying writes', async () => {
        vi.useFakeTimers();
        const response = {
            data: JSON.stringify(guest),
            status: 200,
            headers: {},
        };
        const request = vi
            .fn()
            .mockResolvedValueOnce(response)
            .mockRejectedValueOnce(new HttpNetworkError('Not reachable yet'))
            .mockResolvedValue(response);
        http.setClient({ request });
        const presentation = createPresentation(commonMessages);
        const lifecycle = installAccessLifecycle(presentation);
        dispose = () => {
            lifecycle.dispose();
            presentation.dispose();
        };
        const access = useAccessStore();
        await flushPromises();
        window.dispatchEvent(new Event('offline'));
        window.dispatchEvent(new Event('online'));
        await flushPromises();
        expect(access.status).toBe('offline');
        await vi.advanceTimersByTimeAsync(1_200);
        expect(access.status).toBe('guest');
        expect(access.confirming).toBe(false);
        expect(access.stale).toBe(false);
        expect(request).toHaveBeenCalledTimes(3);
        for (const [config] of request.mock.calls) {
            expect(config).toMatchObject({
                method: 'get',
                url: '/access/context',
            });
        }
    });
    it('spaces failed recovery reads and stops them when their owner is disposed', async () => {
        vi.useFakeTimers();
        const request = vi
            .fn()
            .mockRejectedValue(new HttpNetworkError('Unavailable'));
        http.setClient({ request });
        const presentation = createPresentation(commonMessages);
        const lifecycle = installAccessLifecycle(presentation);
        dispose = () => {
            lifecycle.dispose();
            presentation.dispose();
        };
        await flushPromises();
        await vi.advanceTimersByTimeAsync(3_500);
        expect(useAccessStore().status).toBe('offline');
        expect(request).toHaveBeenCalledTimes(3);
        dispose();
        dispose = () => {};
        await vi.advanceTimersByTimeAsync(30_000);
        expect(request).toHaveBeenCalledTimes(3);
    });
});

it('keeps local logout pending through language and network recovery until an explicit successful logout', async () => {
    const authenticated = {
        ...guest,
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
    };
    let current: unknown = authenticated;
    const request = vi.fn(() =>
        Promise.resolve({
            data: JSON.stringify(current),
            status: 200,
            headers: {},
        }),
    );
    http.setClient({ request });
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    const presentation = createPresentation(commonMessages);
    const lifecycle = installAccessLifecycle(presentation);
    dispose = () => {
        lifecycle.dispose();
        presentation.dispose();
    };
    const access = useAccessStore();
    await flushPromises();
    expect(access.status).toBe('active');
    lifecycle.signOut();
    expect(access.context).toBeNull();
    expect(access.logoutPending).toBe(true);
    post.mock.calls[0][2]?.onNetworkError?.(new HttpNetworkError('Offline'));
    window.dispatchEvent(new Event('online'));
    presentation.i18n.global.locale.value = 'en';
    await flushPromises();
    await lifecycle.resumeAccess();
    expect(access.context).toBeNull();
    expect(access.logoutPending).toBe(true);
    expect(post).toHaveBeenCalledTimes(1);
    lifecycle.signOut();
    expect(post).toHaveBeenCalledTimes(2);
    current = guest;
    lifecycle.finishLogout();
    await flushPromises();
    expect(access.status).toBe('guest');
    expect(access.logoutPending).toBe(false);
});

it('does not let an aborted older context failure mark a successful recovery offline', async () => {
    let reject!: (error: Error) => void;
    const late = new Promise<HttpResponse>((_resolve, fail) => {
        reject = fail;
    });
    const request = vi
        .fn()
        .mockReturnValueOnce(late)
        .mockResolvedValue({
            data: JSON.stringify(guest),
            status: 200,
            headers: {},
        });
    http.setClient({ request });
    const presentation = createPresentation(commonMessages);
    const lifecycle = installAccessLifecycle(presentation);
    dispose = () => {
        lifecycle.dispose();
        presentation.dispose();
    };
    await flushPromises();
    await lifecycle.verify(true);
    expect(useAccessStore().status).toBe('guest');
    reject(new HttpNetworkError('Superseded context request'));
    await flushPromises();
    expect(useAccessStore().status).toBe('guest');
    expect(useAccessStore().stale).toBe(false);
});

it('keeps pending logout in memory without writing browser storage', async () => {
    const fixture = setup();
    await flushPromises();
    const storage = vi
        .spyOn(Storage.prototype, 'setItem')
        .mockImplementation(() => {
            throw new Error('Storage blocked');
        });
    fixture.lifecycle.beginLogout();
    expect(fixture.access.context).toBeNull();
    expect(fixture.access.logoutPending).toBe(true);
    expect(storage).not.toHaveBeenCalled();
    expect(fixture.access.status).toBe('signingOut');
});

it('resets logout flags before reusing the same store in a new lifecycle', async () => {
    const authenticated = {
        ...guest,
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
    } as const;
    const fixture = setup();
    await flushPromises();
    fixture.access.accept(authenticated);
    const generation = fixture.access.generation;
    const cleanup = vi.fn();
    fixture.access.own(cleanup);
    const response = http
        .getClient()
        .request({ url: '/delayed', method: 'get' });
    const observed =
        expect(response).rejects.toBeInstanceOf(HttpCancelledError);
    const oldResponse = {
        data: 'old private response',
        status: 200,
        headers: {},
    };
    fixture.access.setLogoutPending(true);
    fixture.access.logoutSubmitting = true;

    try {
        dispose();
        dispose = () => {};
        expect(useAccessStore()).toBe(fixture.access);
        expect(fixture.access.logoutPending).toBe(false);
        expect(fixture.access.logoutSubmitting).toBe(false);
        expect(fixture.access.context).toBeNull();
        expect(fixture.access.blockedToken).toBeNull();
        expect(fixture.access.status).toBe('checking');
        expect(fixture.access.confirming).toBe(true);
        expect(fixture.access.stale).toBe(true);
        expect(fixture.access.transport).toBe('disconnected');
        expect(fixture.access.generation).toBeGreaterThan(generation);
        expect(cleanup).toHaveBeenCalledTimes(1);

        const request = vi.fn(() =>
            Promise.resolve({
                data: JSON.stringify(authenticated),
                status: 200,
                headers: {},
            }),
        );
        http.setClient({ request });
        const presentation = createPresentation(commonMessages);
        const lifecycle = installAccessLifecycle(presentation);
        dispose = () => {
            lifecycle.dispose();
            presentation.dispose();
        };
        await flushPromises();
        expect(useAccessStore()).toBe(fixture.access);
        expect(fixture.access.status).toBe('active');
        expect(fixture.access.context).toEqual(authenticated);
        expect(request).toHaveBeenCalledTimes(1);

        fixture.resolve(oldResponse);
        await observed;
        expect(fixture.access.status).toBe('active');
        expect(fixture.access.context).toEqual(authenticated);
        expect(fixture.access.generation).toBeGreaterThan(generation);
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        lifecycle.signOut();
        expect(post).toHaveBeenCalledTimes(1);
        expect(fixture.access.logoutPending).toBe(true);
        expect(fixture.access.logoutSubmitting).toBe(true);
    } finally {
        fixture.resolve(oldResponse);
        await observed;
    }
});

it('preserves both logout flags during ordinary invalidation', () => {
    const access = useAccessStore();
    const generation = access.generation;
    const cleanup = vi.fn();
    access.own(cleanup);
    access.setLogoutPending(true);
    access.logoutSubmitting = true;

    access.invalidate();
    expect(access.logoutPending).toBe(true);
    expect(access.logoutSubmitting).toBe(true);
    expect(access.generation).toBeGreaterThan(generation);
    expect(cleanup).toHaveBeenCalledTimes(1);

    const nextGeneration = access.generation;
    access.invalidate(true);
    expect(access.logoutPending).toBe(true);
    expect(access.logoutSubmitting).toBe(true);
    expect(access.status).toBe('signingOut');
    expect(access.generation).toBeGreaterThan(nextGeneration);
    expect(cleanup).toHaveBeenCalledTimes(1);
});
