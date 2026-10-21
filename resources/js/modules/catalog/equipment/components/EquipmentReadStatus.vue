<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { toRef } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import { useActivityIndicator } from '@/composables/useActivityIndicator';

const props = defineProps<{ verifying: boolean }>();
const { t } = useI18n();
const indicator = useActivityIndicator(toRef(props, 'verifying'));
</script>

<template>
    <span
        class="pointer-events-none absolute top-1/2 right-0 -translate-y-1/2 text-muted-foreground"
        role="status"
        data-test="catalog-verification"
        :title="indicator.visible.value ? t('catalog.loading') : undefined"
    >
        <Spinner v-if="indicator.visible.value" />
        <span class="sr-only">{{
            indicator.visible.value ? t('catalog.loading') : ''
        }}</span>
    </span>
</template>
