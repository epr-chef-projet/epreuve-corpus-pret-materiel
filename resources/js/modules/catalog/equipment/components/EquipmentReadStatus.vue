<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{ stale: boolean; refreshing: boolean }>();
const { t } = useI18n();
const messages = ['catalog.unverified', 'catalog.verified'];
</script>

<template>
    <div class="flex items-start gap-3 text-sm text-muted-foreground">
        <p
            class="grid flex-1"
            role="status"
            :title="stale ? t('catalog.stale') : undefined"
            :aria-label="stale ? t('catalog.stale') : undefined"
        >
            <span
                v-for="message in messages"
                :key="message"
                class="invisible col-start-1 row-start-1 before:content-[attr(data-sizing-text)]"
                :data-sizing-text="t(message)"
                aria-hidden="true"
            />
            <span class="col-start-1 row-start-1">{{
                t(stale ? 'catalog.unverified' : 'catalog.verified')
            }}</span>
        </p>
        <span class="relative mt-0.5 size-4 shrink-0" aria-hidden="true">
            <span
                v-if="refreshing"
                class="absolute inset-0 animate-spin rounded-full border-2 border-current border-r-transparent motion-reduce:animate-none"
            />
        </span>
        <span class="sr-only" role="status">{{
            refreshing ? t('catalog.loading') : ''
        }}</span>
    </div>
</template>
