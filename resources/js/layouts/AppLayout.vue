<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import {
    AccessBoundary,
    CurrentAccount,
    SessionStatus,
} from '@/modules/identity-access/public/access';
import LanguageSelector from '@/shared/i18n/LanguageSelector.vue';
import type { BreadcrumbItem } from '@/types';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <AccessBoundary>
        <AppLayout :breadcrumbs="breadcrumbs">
            <a
                href="#main-content"
                class="sr-only focus:not-sr-only focus:p-3"
                >{{ t('common.skip') }}</a
            >
            <div
                class="flex flex-wrap items-center justify-between gap-3 px-4 py-2"
            >
                <CurrentAccount />
                <SessionStatus />
                <LanguageSelector />
            </div>
            <slot />
        </AppLayout>
    </AccessBoundary>
</template>
