import type Echo from 'laravel-echo';
import type { InjectionKey } from 'vue';
import type { NotificationEnvelopeData } from '@/types/generated/types/App/Shared/Realtime';
import type { SessionContextData } from '@/types/generated/types/App/Modules/IdentityAccess/Access/Data';
import type { useAccessStore } from '../stores/useAccessStore';

type Observer = {
    onSubscribed: () => void;
    onStale: () => void;
    onMessage: (message: NotificationEnvelopeData) => void;
};
type Consumer = Observer & {
    active: boolean;
    invalid: boolean;
    generation: number;
    messages: { value: NotificationEnvelopeData; bytes: number }[];
};
type Group = { subscribed: boolean; consumers: Set<Consumer> };
const types = [
    'request.snapshot',
    'document.snapshot',
    'catalog.invalidated',
    'requests.invalidated',
];

export function decodeEnvelope(
    value: unknown,
    generation: number,
): NotificationEnvelopeData | null {
    if (!value || typeof value !== 'object' || Array.isArray(value))
        return null;
    if (Object.keys(value).length !== 5) return null;
    if (
        !('contractVersion' in value) ||
        value.contractVersion !== 1 ||
        !('authGeneration' in value) ||
        value.authGeneration !== generation ||
        !('eventId' in value) ||
        typeof value.eventId !== 'string' ||
        !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(
            value.eventId,
        ) ||
        !('type' in value) ||
        typeof value.type !== 'string' ||
        !types.includes(value.type) ||
        !('payload' in value) ||
        !value.payload ||
        typeof value.payload !== 'object' ||
        Array.isArray(value.payload)
    )
        return null;
    // The projection owner must additionally validate its concrete payload before use.
    return value as NotificationEnvelopeData;
}

export function createSessionSubscriptions(
    echo: Echo<'reverb'>,
    access: ReturnType<typeof useAccessStore>,
) {
    const groups = new Map<string, Group>();
    let count = 0;
    let bytes = 0;
    function clear(consumer: Consumer) {
        for (const entry of consumer.messages) {
            count--;
            bytes -= entry.bytes;
        }
        consumer.messages = [];
    }
    function degrade() {
        for (const group of groups.values()) {
            group.subscribed = false;
            for (const consumer of group.consumers) {
                clear(consumer);
                consumer.invalid = true;
                consumer.active = false;
                consumer.onStale();
            }
        }
    }
    function acquire(context: SessionContextData, observer: Observer) {
        if (!context.user || !context.broadcastToken || !context.authGeneration)
            throw new Error('A verified session is required');
        const token = context.broadcastToken;
        const route = `epr.session.${token}`;
        const consumer: Consumer = {
            ...observer,
            active: false,
            invalid: false,
            generation: access.generation,
            messages: [],
        };
        const current = () =>
            consumer.generation === access.generation &&
            access.context?.broadcastToken === token;
        let group = groups.get(route);
        if (!group) {
            group = { subscribed: false, consumers: new Set() };
            groups.set(route, group);
            const installed = group;
            const channel = echo.private(route);
            channel.listen('.epr.notification', (raw: unknown) => {
                if (!current()) return;
                let size: number;
                try {
                    size = new TextEncoder().encode(JSON.stringify(raw)).length;
                } catch {
                    size = Infinity;
                }
                const message =
                    size <= 16 * 1024
                        ? decodeEnvelope(raw, context.authGeneration!)
                        : null;
                if (!message) {
                    for (const target of installed.consumers) {
                        clear(target);
                        target.invalid = true;
                        target.active = false;
                        target.onStale();
                    }
                    return;
                }
                for (const target of installed.consumers) {
                    if (
                        target.generation !== access.generation ||
                        target.invalid
                    )
                        continue;
                    if (target.active && installed.subscribed)
                        target.onMessage(message);
                    else {
                        target.messages.push({ value: message, bytes: size });
                        count++;
                        bytes += size;
                    }
                }
                if (count > 100 || bytes > 256 * 1024) {
                    for (const item of groups.values())
                        for (const target of item.consumers) {
                            clear(target);
                            target.invalid = true;
                            target.active = false;
                            target.onStale();
                        }
                }
            });
            channel.subscribed(() => {
                if (!current()) return;
                installed.subscribed = true;
                for (const target of installed.consumers) target.onSubscribed();
            });
            channel.error(() => {
                installed.subscribed = false;
                for (const target of installed.consumers) {
                    clear(target);
                    target.invalid = true;
                    target.active = false;
                    target.onStale();
                }
            });
        }
        group.consumers.add(consumer);
        const ownedGroup = group;
        if (group.subscribed)
            queueMicrotask(() => {
                if (current() && group.consumers.has(consumer))
                    consumer.onSubscribed();
            });
        let released = false;
        let disown = () => {};
        function release() {
            if (released) return;
            released = true;
            clear(consumer);
            ownedGroup.consumers.delete(consumer);
            disown();
            if (ownedGroup.consumers.size === 0) {
                echo.leave(route);
                groups.delete(route);
            }
        }
        disown = access.own(release);
        return {
            settleRead() {
                if (released || !current() || consumer.invalid) return false;
                consumer.active = group.subscribed;
                const messages = [...consumer.messages];
                clear(consumer);
                for (const entry of messages) {
                    if (current()) consumer.onMessage(entry.value);
                }
                return true;
            },
            capture() {
                clear(consumer);
                consumer.invalid = false;
                consumer.active = false;
            },
            activate(install: () => void) {
                if (
                    released ||
                    !current() ||
                    consumer.invalid ||
                    !group.subscribed
                )
                    return false;
                install();
                consumer.active = true;
                const messages = [...consumer.messages];
                clear(consumer);
                for (const entry of messages) {
                    if (current()) consumer.onMessage(entry.value);
                }
                return true;
            },
            release,
        };
    }
    return {
        acquire,
        degrade,
        dispose() {
            for (const [route, group] of groups) {
                for (const consumer of group.consumers) clear(consumer);
                echo.leave(route);
            }
            groups.clear();
        },
    };
}

export type SessionSubscriptions = ReturnType<
    typeof createSessionSubscriptions
>;
export const sessionSubscriptionsKey: InjectionKey<SessionSubscriptions | null> =
    Symbol('session-subscriptions');
