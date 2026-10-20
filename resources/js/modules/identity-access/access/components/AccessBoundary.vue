<script setup lang="ts">
import { computed, inject, nextTick, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { login } from '@/routes';
import { accessLifecycleKey } from '../lifecycleKey';
import { useAccessStore } from '../stores/useAccessStore';

const page = usePage();
const access = useAccessStore();
const lifecycle = inject(accessLifecycleKey);
const { t } = useI18n();
let focusedUrl = '';
const ownsPage = computed(
    () =>
        Boolean(access.context?.user) && access.matchesContext(page.props.auth),
);
watch(
    () => [page.url, ownsPage.value, access.confirming],
    async () => {
        if (!ownsPage.value || access.confirming || focusedUrl === page.url)
            return;
        focusedUrl = page.url;
        const generation = access.generation;
        await nextTick();
        if (
            generation !== access.generation ||
            !ownsPage.value ||
            access.confirming
        )
            return;
        const heading = document.querySelector<HTMLElement>('#main-content h1');
        heading?.setAttribute('tabindex', '-1');
        heading?.focus({ preventScroll: true });
    },
    { immediate: true },
);
</script>

<template>
    <div>
        <div
            v-if="ownsPage"
            v-show="!access.confirming"
            :inert="access.status !== 'active' || access.stale"
        >
            <slot />
        </div>
        <div
            v-if="!ownsPage || access.confirming"
            class="flex min-h-svh flex-col items-center justify-center gap-4 p-6"
        >
            <p role="status">
                {{
                    t(
                        access.status === 'guest'
                            ? 'issues.identity_access.authentication_required'
                            : access.status === 'offline'
                              ? 'common.offline'
                              : access.status === 'signingOut'
                                ? 'common.signingOut'
                                : 'common.checking',
                    )
                }}
            </p>
            <button
                class="rounded-md border px-4 py-2"
                type="button"
                @click="lifecycle?.resumeAccess()"
            >
                {{ t('common.refresh') }}
            </button>
            <Link v-if="access.status === 'guest'" :href="login()">{{
                t('identity.signIn')
            }}</Link>
        </div>
        <p
            v-if="ownsPage && (access.status === 'offline' || access.stale)"
            role="status"
            class="fixed inset-x-0 bottom-0 border-t bg-background p-4 text-center"
        >
            {{
                t(
                    access.status === 'offline'
                        ? 'common.offline'
                        : 'common.stale',
                )
            }}
        </p>
    </div>
</template>
