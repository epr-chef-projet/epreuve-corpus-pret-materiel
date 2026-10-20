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
});
