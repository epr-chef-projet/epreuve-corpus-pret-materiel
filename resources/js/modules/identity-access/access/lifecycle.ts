import { http, HttpCancelledError, HttpNetworkError } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import type { Presentation } from '@/shared/i18n/presentation';
import { isContextRequest, readContext } from './requests/readContext';
import { useAccessStore } from './stores/useAccessStore';

export function installAccessLifecycle(presentation: Presentation) {
    const access = useAccessStore();
    const channel =
        typeof BroadcastChannel === 'undefined'
            ? null
            : new BroadcastChannel('epr.access');
    const client = http.getClient();
    let disposed = false;
    let sequence = 0;
    let pending: AbortController | null = null;
    let interval: ReturnType<typeof setTimeout> | undefined;
    let freshness: ReturnType<typeof setTimeout> | undefined;
    let locked = false;

    http.setClient({
        async request(config) {
            const generation = access.generation;
            const abort = new AbortController();
            const cancel = () => abort.abort();
            config.signal?.addEventListener('abort', cancel, { once: true });
            if (config.signal?.aborted) abort.abort();
            const release = access.own(cancel);
            try {
                const response = await client.request({
                    ...config,
                    signal: abort.signal,
                    headers: {
                        ...config.headers,
                        'X-Presentation-Locale':
                            presentation.i18n.global.locale.value,
                    },
                });
                if (
                    generation === access.generation &&
                    !isContextRequest(config.url) &&
                    (response.status === 401 || response.status === 419)
                ) {
                    invalidate();
                    channel?.postMessage('changed');
                    void verify(true);
                }
                if (generation !== access.generation)
                    throw new HttpCancelledError();
                return response;
            } catch (error) {
                if (generation !== access.generation)
                    throw new HttpCancelledError();
                if (error instanceof HttpNetworkError) access.offline();
                throw error;
            } finally {
                release();
                config.signal?.removeEventListener('abort', cancel);
            }
        },
    });

    function scrubPage() {
        router.cancelAll();
        router.flushAll();
        router.clearHistory();
        router.replace({
            props: (props) => ({
                name: props.name,
                reverb: props.reverb,
                sidebarOpen: true,
                locale: presentation.i18n.global.locale.value,
                auth: {
                    user: null,
                    broadcastToken: null,
                    authGeneration: null,
                    expiresAt: null,
                },
                errors: {},
            }),
        });
    }
    function invalidate(signingOut = false) {
        sequence++;
        pending?.abort();
        pending = null;
        clearTimeout(freshness);
        access.invalidate(signingOut);
        document.cookie = 'sidebar_state=;path=/;max-age=0;SameSite=Lax';
        scrubPage();
    }
    function schedule() {
        clearTimeout(interval);
        if (!disposed && document.visibilityState !== 'hidden') {
            interval = setTimeout(
                () => {
                    void verify();
                },
                13_000 + Math.random() * 2_000,
            );
        }
    }
    async function verify(force = false) {
        if (disposed || (pending && !force)) return;
        pending?.abort();
        const current = ++sequence;
        const generation = access.generation;
        const abort = new AbortController();
        pending = abort;
        const timeout = setTimeout(() => abort.abort(), 10_000);
        try {
            const data = await readContext(abort.signal);
            if (current !== sequence || generation !== access.generation)
                return;
            if (
                locked &&
                data.user &&
                data.broadcastToken === access.blockedToken
            )
                return;
            const changed =
                access.context !== null && !access.matchesContext(data);
            if (changed) {
                invalidate();
                channel?.postMessage('changed');
            }
            locked = false;
            access.accept(data);
            document.documentElement.style.visibility = '';
            clearTimeout(freshness);
            freshness = setTimeout(access.markStale, 20_000);
            if (changed) {
                schedule();
                if (data.user)
                    router.visit(window.location.href, { replace: true });
            }
        } catch {
            if (current === sequence && generation === access.generation)
                access.offline();
        } finally {
            clearTimeout(timeout);
            if (current === sequence || pending === abort) {
                pending = null;
                schedule();
            }
        }
    }
    function receive(event: MessageEvent<unknown>) {
        if (event.data !== 'blocked' && event.data !== 'changed') return;
        locked = event.data === 'blocked';
        invalidate(locked);
        if (!locked)
            void verify(true).then(() => {
                if (access.context?.user)
                    router.visit(window.location.href, { replace: true });
            });
        else schedule();
    }
    channel?.addEventListener('message', receive);
    function beginLogout() {
        locked = true;
        invalidate(true);
        channel?.postMessage('blocked');
    }
    function finishLogout() {
        locked = false;
        channel?.postMessage('changed');
        void verify(true);
    }
    async function resumeAccess() {
        locked = false;
        access.allowResume();
        await verify(true);
        if (
            access.context?.user &&
            !access.matchesContext(usePage().props?.auth)
        )
            router.visit(window.location.href, { replace: true });
    }
    const before = router.on('beforeUpdate', (event) => {
        const auth = event.detail.page.props.auth;
        if (!auth) return;
        if (
            access.context ? !access.matchesContext(auth) : Boolean(auth?.user)
        ) {
            sequence++;
            access.invalidate();
            channel?.postMessage('changed');
            void verify(true);
        }
    });
    const network = router.on('networkError', (event) => {
        access.offline();
        event.preventDefault();
    });
    const locale = watch(presentation.i18n.global.locale, () => {
        void verify(true);
    });
    function resume() {
        if (document.visibilityState === 'hidden') {
            clearTimeout(interval);
            return;
        }
        access.confirm();
        document.documentElement.style.visibility = '';
        void verify(true);
    }
    function hide() {
        if (access.context?.user)
            document.documentElement.style.visibility = 'hidden';
    }
    window.addEventListener('online', resume);
    window.addEventListener('offline', access.offline);
    window.addEventListener('pageshow', resume);
    window.addEventListener('pagehide', hide);
    document.addEventListener('visibilitychange', resume);
    void verify();
    return {
        verify,
        resumeAccess,
        beginLogout,
        finishLogout,
        dispose() {
            disposed = true;
            sequence++;
            pending?.abort();
            clearTimeout(interval);
            clearTimeout(freshness);
            before();
            network();
            locale();
            channel?.removeEventListener('message', receive);
            channel?.close();
            window.removeEventListener('online', resume);
            window.removeEventListener('offline', access.offline);
            window.removeEventListener('pageshow', resume);
            window.removeEventListener('pagehide', hide);
            document.removeEventListener('visibilitychange', resume);
            access.$reset();
            http.setClient(client);
        },
    };
}

export type AccessLifecycle = ReturnType<typeof installAccessLifecycle>;
