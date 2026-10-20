<script setup lang="ts">
import { inject, onScopeDispose } from 'vue';
import { useI18n } from 'vue-i18n';
import { accessLifecycleKey } from '../lifecycleKey';
import { sessionSubscriptionsKey } from '../notifications/sessionSubscriptions';
import { useAccessStore } from '../stores/useAccessStore';

const subscriptions = inject(sessionSubscriptionsKey, null);
const lifecycle = inject(accessLifecycleKey);
const access = useAccessStore();
const { t } = useI18n();
let disposed = false;
let busy = false;
let dirty = false;
let attempts = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
const generation = access.generation;
const current = () => !disposed && generation === access.generation;

function schedule(delay = 100) {
    dirty = true;
    if (!current() || busy || timer) return;
    timer = setTimeout(() => {
        timer = undefined;
        void recover();
    }, delay);
}
async function recover() {
    if (!current() || !lifecycle || !consumer) return;
    busy = true;
    dirty = false;
    consumer.capture();
    await lifecycle.verify(true);
    if (!current()) return;
    const installed =
        access.status === 'active' &&
        !access.stale &&
        consumer.activate(() => {});
    access.setTransport(installed ? 'subscribed' : 'subscribing');
    busy = false;
    if (!installed || dirty)
        schedule(++attempts < 3 ? 100 : 15_000 + Math.random() * 2000);
    else attempts = 0;
}
const consumer =
    subscriptions && access.context?.user
        ? subscriptions.acquire(access.context, {
              onSubscribed: () => schedule(0),
              onStale: () => {
                  access.setTransport('disconnected');
                  schedule(15_000);
              },
              onMessage: () => schedule(),
          })
        : null;
onScopeDispose(() => {
    disposed = true;
    clearTimeout(timer);
    consumer?.release();
});
</script>

<template>
    <p
        role="status"
        class="text-sm text-muted-foreground"
        data-test="session-transport"
    >
        {{
            access.transport === 'subscribed'
                ? t('common.transportReady')
                : t('common.degraded')
        }}
    </p>
</template>
