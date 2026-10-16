<script setup lang="ts">
import { inject, onBeforeUnmount, onMounted, ref } from 'vue';
import TwoFactorQrCode from '@/components/TwoFactorQrCode.vue';
import { echoKey } from '@/shared/realtime';

defineOptions({ name: 'FoundationProbePage' });
const props = defineProps<{ probeUserId: string; csrfToken: string }>();
const echo = inject(echoKey);
const subscribed = ref(false);
const received = ref(false);
const channelName = `foundation.probe.${props.probeUserId}`;
const svg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"><script>window.qrExecuted=true<' +
    '/script><image href="https://qr-fixture.invalid/external.png"/><rect width="20" height="20" fill="black"/></svg>';

function connect() {
    if (!echo) throw new Error('Realtime transport required by fixture');
    echo.private(channelName)
        .subscribed(() => {
            subscribed.value = true;
        })
        .listen('.ProbePublished', (payload: unknown) => {
            received.value =
                typeof payload === 'object' &&
                payload !== null &&
                'marker' in payload &&
                payload.marker === 'server-marker';
        });
}
async function publish() {
    const response = await fetch('/__foundation/publish', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': props.csrfToken,
            Accept: 'application/json',
        },
    });
    if (!response.ok) throw new Error('Publication failed');
}
function leave() {
    echo?.leave(channelName);
    subscribed.value = false;
}
onMounted(connect);
onBeforeUnmount(leave);
</script>

<template>
    <main>
        <h1>Foundation probe</h1>
        <p data-test="subscribed">{{ subscribed }}</p>
        <p data-test="received">{{ received }}</p>
        <button @click="publish">Publish</button>
        <button @click="leave">Leave</button>
        <TwoFactorQrCode :svg="svg" />
    </main>
</template>
