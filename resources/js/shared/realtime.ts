import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { InjectionKey } from 'vue';

export interface ReverbPublicConfig {
    key: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
}

export const echoKey: InjectionKey<Echo<'reverb'> | null> = Symbol('echo');

export function createEcho(
    config: ReverbPublicConfig | null,
): Echo<'reverb'> | null {
    if (config === null) return null;
    if (
        !config.key ||
        !config.host ||
        !Number.isInteger(config.port) ||
        config.port < 1 ||
        config.port > 65535 ||
        !['http', 'https'].includes(config.scheme)
    ) {
        throw new Error('Invalid public realtime configuration');
    }
    const client = new Pusher(config.key, {
        cluster: 'local',
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        channelAuthorization: {
            endpoint: '/broadcasting/auth',
            transport: 'ajax',
        },
    });
    return new Echo<'reverb'>({ broadcaster: 'reverb', client });
}
