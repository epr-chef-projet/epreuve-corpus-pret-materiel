import {
    computed,
    inject,
    onScopeDispose,
    ref,
    shallowRef,
    watch,
    type Ref,
    type ShallowRef,
} from 'vue';
import {
    sessionSubscriptionsKey,
    useAccessStore,
} from '@/modules/identity-access/public/access';
import type { NotificationEnvelopeData } from '@/types/generated/types/App/Shared/Realtime';
import { isCatalogInvalidation, record } from '../contracts/decoders';

export function useCatalogProjection<T>(
    initial: Ref<T>,
    identity: () => string,
    read: (signal: AbortSignal) => Promise<T>,
    accepts: (incoming: T, current: T) => boolean = () => true,
) {
    const access = useAccessStore();
    const subscriptions = inject(sessionSubscriptionsKey, null);
    const generation = access.generation;
    const data: ShallowRef<T> = shallowRef<T>(initial.value);
    let dataIdentity = identity();
    const busy = ref(false);
    const stale = ref(true);
    const expired = ref(false);
    const failed = ref(false);
    const manualRefreshing = ref(false);
    let disposed = false;
    let epoch = 0;
    let attempts = 0;
    let dirty = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let periodic: ReturnType<typeof setTimeout> | undefined;
    let freshness: ReturnType<typeof setTimeout> | undefined;
    let abort: AbortController | null = null;
    let consumer: ReturnType<
        NonNullable<typeof subscriptions>['acquire']
    > | null = null;
    const current = () => !disposed && generation === access.generation;
    function trackFreshness() {
        clearTimeout(freshness);
        expired.value = false;
        freshness = setTimeout(() => {
            if (current()) {
                stale.value = true;
                expired.value = true;
            }
        }, 20_000);
    }
    function schedule(delay = 100) {
        dirty = true;
        if (
            !current() ||
            busy.value ||
            timer ||
            document.visibilityState === 'hidden'
        )
            return;
        timer = setTimeout(() => {
            timer = undefined;
            void recover();
        }, delay);
    }
    function message(value: NotificationEnvelopeData) {
        if (value.type !== 'catalog.invalidated') return;
        if (!isCatalogInvalidation(value.payload)) {
            epoch++;
            stale.value = true;
            schedule();
            return;
        }
        if (
            record(value.payload) &&
            value.payload.projection === 'catalogManagement'
        ) {
            epoch++;
            schedule();
        }
    }
    function poll() {
        clearTimeout(periodic);
        if (current() && document.visibilityState !== 'hidden')
            periodic = setTimeout(
                () => {
                    schedule(0);
                    poll();
                },
                13_000 + Math.random() * 2000,
            );
    }
    async function recover() {
        if (!current() || busy.value || document.visibilityState === 'hidden')
            return;
        busy.value = true;
        dirty = false;
        const observedEpoch = epoch;
        const key = identity();
        const controller = new AbortController();
        let failedRead = false;
        abort = controller;
        consumer?.capture();
        try {
            const value = await read(controller.signal);
            if (!current() || controller.signal.aborted || key !== identity())
                return;
            const captured = consumer ? consumer.settleRead() : true;
            if (
                !captured ||
                observedEpoch !== epoch ||
                (key === dataIdentity && !accepts(value, data.value))
            ) {
                dirty = true;
                return;
            }
            data.value = value;
            dataIdentity = key;
            stale.value = false;
            failed.value = false;
            attempts = 0;
            trackFreshness();
        } catch {
            if (current() && !controller.signal.aborted) {
                failedRead = true;
                failed.value = true;
                stale.value = true;
                dirty = true;
            }
        } finally {
            if (current()) {
                busy.value = false;
                abort = null;
                if (dirty)
                    schedule(
                        ++attempts < 3 ? 100 : 15_000 + Math.random() * 2000,
                    );
                if (!dirty || failedRead || attempts >= 3)
                    manualRefreshing.value = false;
            }
        }
    }
    if (subscriptions && access.context?.user)
        consumer = subscriptions.acquire(access.context, {
            onSubscribed: () => {
                epoch++;
                stale.value = true;
                schedule(0);
            },
            onStale: () => {
                epoch++;
                stale.value = true;
                schedule(15_000);
            },
            onMessage: message,
        });
    const stopInitial = watch(initial, (value) => {
        if (current()) {
            const key = identity();
            if (key !== dataIdentity || accepts(value, data.value)) {
                data.value = value;
                dataIdentity = key;
            }
            stale.value = true;
            failed.value = false;
            manualRefreshing.value = false;
            trackFreshness();
            epoch++;
            abort?.abort();
            schedule(0);
        }
    });
    const stopIdentity = watch(identity, () => {
        if (!current()) return;
        epoch++;
        stale.value = true;
        manualRefreshing.value = false;
        abort?.abort();
        schedule(0);
    });
    function visible() {
        clearTimeout(periodic);
        clearTimeout(timer);
        timer = undefined;
        if (document.visibilityState !== 'hidden') {
            epoch++;
            stale.value = true;
            schedule(0);
            poll();
        }
    }
    function dispose() {
        if (disposed) return;
        disposed = true;
        manualRefreshing.value = false;
        abort?.abort();
        clearTimeout(timer);
        clearTimeout(periodic);
        clearTimeout(freshness);
        consumer?.release();
        stopInitial();
        stopIdentity();
        document.removeEventListener('visibilitychange', visible);
        window.removeEventListener('online', visible);
    }
    document.addEventListener('visibilitychange', visible);
    window.addEventListener('online', visible);
    const disown = access.own(dispose);
    onScopeDispose(() => {
        disown();
        dispose();
    });
    trackFreshness();
    schedule(0);
    poll();
    return {
        data,
        busy,
        stale,
        expired,
        verifying: computed(
            () => stale.value && !expired.value && !failed.value,
        ),
        manualRefreshing,
        failed,
        refresh: () => {
            if (!current()) return;
            clearTimeout(timer);
            timer = undefined;
            attempts = 0;
            epoch++;
            manualRefreshing.value = true;
            schedule(0);
        },
        recheck: () => {
            if (!current()) return;
            epoch++;
            schedule(0);
        },
    };
}
