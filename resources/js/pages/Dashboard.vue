<script setup lang="ts">
defineOptions({ name: 'AccessDashboardPage' });
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { PendingCatalogOperations } from '@/modules/catalog/public/equipment';
import { useI18n } from 'vue-i18n';
const props = defineProps<{
    section?: 'catalog' | 'ownRequests' | 'teamRequests' | 'management';
}>();
const page = usePage();
const { t } = useI18n();
const title = computed(() =>
    t(
        'identity.' +
            (props.section === 'catalog'
                ? 'catalog'
                : (props.section ??
                  (page.props.auth.user?.role === 'borrower'
                      ? 'catalog'
                      : 'teamRequests'))),
    ),
);
</script>

<template>
    <section class="flex flex-col gap-4 p-4 sm:p-6">
        <Head :title="title" />
        <h1 class="text-2xl font-semibold">{{ title }}</h1>

        <PendingCatalogOperations />
        <section
            class="flex flex-col gap-3 rounded-lg border p-5"
            aria-labelledby="partial-title"
        >
            <h2 id="partial-title" class="text-lg font-medium">
                {{ t('common.partialTitle') }}
            </h2>
            <p role="status">{{ t('common.partial') }}</p>
            <p>{{ t('common.noAvailability') }}</p>
        </section>
        <p class="text-sm text-muted-foreground">{{ t('common.timezone') }}</p>
    </section>
</template>
