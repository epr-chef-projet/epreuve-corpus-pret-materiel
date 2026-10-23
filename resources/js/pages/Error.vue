<script setup lang="ts">
defineOptions({ name: 'PublicErrorPage' });
import { computed } from 'vue';
import { PendingCatalogOperations } from '@/modules/catalog/public/equipment';
import { useAccessStore } from '@/modules/identity-access/public/access';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSelector from '@/shared/i18n/LanguageSelector.vue';
import { explainIssue } from '@/shared/i18n/issue';
import { home, login } from '@/routes';
const props = defineProps<{ issue: unknown }>();
const composer = useI18n();
const access = useAccessStore();
const page = usePage();
const mayVerify = computed(
    () =>
        access.status === 'active' &&
        !access.confirming &&
        !access.stale &&
        access.matchesContext(page.props.auth),
);
const { t } = composer;
const explanation = computed(() => explainIssue(props.issue, composer));
</script>

<template>
    <main
        class="mx-auto flex min-h-svh max-w-xl flex-col justify-center gap-5 p-6"
    >
        <Head :title="t('common.error')" />
        <LanguageSelector />
        <h1 v-focus tabindex="-1" class="text-2xl font-semibold">
            {{ t('common.error') }}
        </h1>
        <p role="alert">{{ explanation.message }}</p>
        <p>{{ t('common.' + explanation.outcome) }}</p>
        <p>{{ explanation.next }}</p>
        <p v-if="explanation.correlationId" class="text-sm break-all">
            {{ t('common.correlation') }} : {{ explanation.correlationId }}
        </p>
        <PendingCatalogOperations v-if="mayVerify" />
        <Link class="underline" :href="explanation.signIn ? login() : home()">{{
            t(explanation.signIn ? 'identity.signIn' : 'common.home')
        }}</Link>
    </main>
</template>
