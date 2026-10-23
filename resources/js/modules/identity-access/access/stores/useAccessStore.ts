import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import type { SessionContextData } from '@/types/generated/types/App/Modules/IdentityAccess/Access/Data';

export const useAccessStore = defineStore('identity-access.context', () => {
    const context = shallowRef<SessionContextData | null>(null);
    const generation = ref(0);
    const status = ref<
        'checking' | 'active' | 'guest' | 'offline' | 'signingOut'
    >('checking');
    const logoutPending = ref(false);
    const logoutSubmitting = ref(false);
    const confirming = ref(true);
    const stale = ref(true);
    const transport = ref<'disconnected' | 'subscribing' | 'subscribed'>(
        'disconnected',
    );
    const blockedToken = ref<string | null>(null);
    const cleanup = new Set<() => void>();

    function own(dispose: () => void) {
        cleanup.add(dispose);
        return () => {
            cleanup.delete(dispose);
        };
    }
    function invalidate(signingOut = false) {
        generation.value++;
        blockedToken.value =
            context.value?.broadcastToken ?? blockedToken.value;
        context.value = null;
        confirming.value = true;
        stale.value = true;
        transport.value = 'disconnected';
        status.value = signingOut ? 'signingOut' : 'checking';
        for (const dispose of [...cleanup]) dispose();
        cleanup.clear();
    }
    function matchesContext(
        value:
            | Pick<
                  SessionContextData,
                  'user' | 'broadcastToken' | 'authGeneration'
              >
            | null
            | undefined,
    ) {
        const current = context.value;
        return (
            current !== null &&
            value != null &&
            current.broadcastToken === value.broadcastToken &&
            current.authGeneration === value.authGeneration &&
            current.user?.id === value.user?.id &&
            current.user?.workshopId === value.user?.workshopId &&
            current.user?.role === value.user?.role
        );
    }
    function accept(value: SessionContextData) {
        if (context.value !== null && !matchesContext(value)) invalidate();
        context.value = value;
        blockedToken.value = null;
        confirming.value = false;
        stale.value = false;
        status.value = value.user ? 'active' : 'guest';
    }
    function setLogoutPending(value: boolean) {
        logoutPending.value = value;
    }
    function confirm() {
        confirming.value = true;
    }
    function markStale() {
        stale.value = true;
    }
    function allowResume() {
        blockedToken.value = null;
    }
    function setTransport(value: typeof transport.value) {
        transport.value = value;
    }
    function offline() {
        status.value = 'offline';
        stale.value = true;
    }
    function $reset() {
        invalidate();
        blockedToken.value = null;
    }
    return {
        context,
        generation,
        status,
        confirming,
        logoutPending,
        logoutSubmitting,
        setLogoutPending,
        stale,
        transport,
        blockedToken,
        own,
        invalidate,
        accept,
        matchesContext,
        confirm,
        markStale,
        allowResume,
        setTransport,
        offline,
        $reset,
    };
});
