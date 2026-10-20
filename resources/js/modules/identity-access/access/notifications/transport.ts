import type Echo from 'laravel-echo';
import { createSessionSubscriptions } from './sessionSubscriptions';
import type { useAccessStore } from '../stores/useAccessStore';

export function installSessionTransport(
    echo: Echo<'reverb'>,
    access: ReturnType<typeof useAccessStore>,
) {
    const subscriptions = createSessionSubscriptions(echo, access);
    const pusher = echo.connector.pusher;
    let retry: ReturnType<typeof setTimeout> | undefined;
    let attempt = 0;
    let stopping = false;
    let disposed = false;
    function retryLater() {
        if (retry || disposed) return;
        const delay = [1000, 2000, 5000][attempt++] ?? 15_000;
        retry = setTimeout(
            () => {
                retry = undefined;
                pusher.connect();
            },
            delay * (0.9 + Math.random() * 0.2),
        );
    }
    function stateChanged({ current }: { current: string }) {
        if (disposed || stopping) return;
        if (current === 'connected') {
            attempt = 0;
            clearTimeout(retry);
            retry = undefined;
            return;
        }
        if (!['failed', 'unavailable', 'disconnected'].includes(current))
            return;
        access.setTransport('disconnected');
        subscriptions.degrade();
        // Stop the SDK retry strategy before scheduling the single application retry.
        stopping = true;
        pusher.disconnect();
        stopping = false;
        retryLater();
    }
    const online = () => {
        clearTimeout(retry);
        retry = undefined;
        pusher.connect();
    };
    pusher.connection.bind('state_change', stateChanged);
    window.addEventListener('online', online);
    return {
        subscriptions,
        dispose() {
            disposed = true;
            clearTimeout(retry);
            pusher.connection.unbind('state_change', stateChanged);
            window.removeEventListener('online', online);
            subscriptions.dispose();
        },
    };
}
