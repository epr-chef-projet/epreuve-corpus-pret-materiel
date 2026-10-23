<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { manageCatalog } from '@/routes';
import EquipmentForm from './EquipmentForm.vue';
import type { IssueData } from '@/types/generated/types/App/Shared/Errors';
defineProps<{ issue?: IssueData | null }>();
const { t } = useI18n();
</script>

<template>
    <section class="flex flex-col gap-5 p-4 sm:p-6">
        <Head :title="t('catalog.create')" />
        <h1 class="text-2xl font-semibold">{{ t('catalog.create') }}</h1>
        <Link class="underline" :href="manageCatalog()">{{
            t('catalog.back')
        }}</Link
        ><EquipmentForm :issue="issue" />
    </section>
</template>
