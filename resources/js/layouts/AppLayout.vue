<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import {
    AccessBoundary,
    CurrentAccount,
    SessionStatus,
    useAccessStore,
} from '@/modules/identity-access/public/access';
import LanguageSelector from '@/shared/i18n/LanguageSelector.vue';
import type { BreadcrumbItem } from '@/types';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
const access = useAccessStore();

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:p-3">{{
            t('common.skip')
        }}</a>
        <div
            class="flex flex-wrap items-center justify-between gap-3 px-4 py-2"
        >
            <CurrentAccount />
            <SessionStatus
                v-if="access.context?.user"
                :key="access.generation"
            />
            <LanguageSelector />
        </div>
        <AccessBoundary><slot /></AccessBoundary>
    </AppLayout>
</template>
