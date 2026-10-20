<script setup lang="ts">
defineOptions({ name: 'PublicWelcomePage' });
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSelector from '@/shared/i18n/LanguageSelector.vue';
import { login } from '@/routes';
const { t } = useI18n();
</script>

<template>
    <main
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground"
    >
        <Head :title="t('common.app')" />
        <LanguageSelector />
        <div class="flex max-w-xl flex-col gap-4 rounded-xl border p-6 sm:p-10">
            <p class="text-sm font-semibold">{{ t('common.app') }}</p>
            <h1 class="text-3xl font-semibold">{{ t('common.welcome') }}</h1>
            <p>{{ t('common.intro') }}</p>
            <Link
                :href="login()"
                class="self-start rounded-md bg-primary px-4 py-2 text-primary-foreground focus-visible:outline-2"
                >{{ t('identity.signIn') }}</Link
            >
        </div>
    </main>
</template>
