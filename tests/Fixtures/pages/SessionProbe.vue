<script setup lang="ts">
import { inject, onScopeDispose, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { createEcho, echoKey } from '@/shared/realtime';
import {
    sessionSubscriptionsKey,
    useAccessStore,
} from '@/modules/identity-access/public/access';

declare global {
    interface Window {
        sessionProbeEvents?: number;
        sessionProbeHostileSubscribed?: boolean;
    }
}

const props = defineProps<{ csrfToken: string }>();
const page = usePage();
const sharedEcho = inject(echoKey);
const access = useAccessStore();
const subscriptions = inject(sessionSubscriptionsKey);
const received = ref(0);
const subscribed = ref(false);
const laterReceived = ref(0);
let later: ReturnType<NonNullable<typeof subscriptions>['acquire']> | null =
    null;
const context = access.context;
if (!context?.user || !subscriptions)
    throw new Error('Verified fixture context required');
const first = subscriptions.acquire(context, {
    onSubscribed: () => {
        first.capture();
        subscribed.value = first.activate(() => {});
    },
    onStale: () => {
        subscribed.value = false;
    },
    onMessage: (message) => {
        if (
            message.type === 'catalog.invalidated' &&
            message.payload.projection === 'catalog'
        )
            received.value++;
    },
});
function addConsumer() {
    later = subscriptions!.acquire(context!, {
        onSubscribed: () => {
            later!.capture();
            later!.activate(() => {});
        },
        onStale: () => {},
        onMessage: () => laterReceived.value++,
    });
}
function hostile() {
    const echo = createEcho(page.props.reverb ?? null);
    if (!echo) throw new Error('Realtime fixture unavailable');
    window.sessionProbeEvents = 0;
    echo.private(`epr.session.${context!.broadcastToken}`)
        .listen('.epr.notification', () => {
            window.sessionProbeEvents = (window.sessionProbeEvents ?? 0) + 1;
        })
        .subscribed(() => {
            window.sessionProbeHostileSubscribed = true;
        });
    // This isolated hostile fixture deliberately ignores logout and component cleanup.
    // Closing its browser context terminates the socket.
}
async function publish() {
    const response = await fetch('/__foundation/session-publish', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': props.csrfToken,
            Accept: 'application/json',
        },
    });
    if (response.status !== 202) throw new Error('Fixture dispatch failed');
}
onScopeDispose(() => {
    first.release();
    later?.release();
});
</script>

<template>
    <main id="main-content" tabindex="-1">
        <h1>Session notification fixture</h1>
        <p data-test="session-subscribed">{{ subscribed }}</p>
        <p data-test="session-received">{{ received }}</p>
        <p data-test="later-received">{{ laterReceived }}</p>
        <button @click="publish">Publish session</button>
        <button @click="addConsumer">Add consumer</button>
        <button @click="first.release()">Release first</button>
        <button @click="hostile">Keep hostile socket</button>
        <button @click="sharedEcho?.connector.pusher.disconnect()">
            Disconnect transport
        </button>
    </main>
</template>
