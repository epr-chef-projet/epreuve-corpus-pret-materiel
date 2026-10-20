<script setup lang="ts">
import { inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { presentationKey } from './presentation';

const presentation = inject(presentationKey);
if (!presentation) throw new Error('Presentation plugin is required');
const { t, locale } = useI18n();
function change(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    if (value === 'fr' || value === 'en') presentation?.setLocale(value);
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <label class="flex items-center gap-2 text-sm">
            <span>{{ t('common.language') }}</span>
            <select
                :value="locale"
                class="rounded-md border bg-background px-2 py-1 text-foreground focus-visible:outline-2"
                @change="change"
            >
                <option value="fr">{{ t('common.french') }}</option>
                <option value="en">{{ t('common.english') }}</option>
            </select>
        </label>
        <p
            v-if="presentation.storageUnavailable.value"
            role="status"
            class="max-w-sm text-sm"
        >
            {{ t('common.localeStorage') }}
        </p>
    </div>
</template>
