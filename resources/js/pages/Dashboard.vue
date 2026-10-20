<script setup lang="ts">
defineOptions({ name: 'AccessDashboardPage' });
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
        <dl
            v-if="page.props.auth.user"
            class="flex flex-wrap gap-x-6 gap-y-3 rounded-md border p-4"
        >
            <div>
                <dt class="text-sm text-muted-foreground">
                    {{ t('identity.workshop') }}
                </dt>
                <dd>{{ page.props.auth.user.workshopName }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    {{ t('identity.account') }}
                </dt>
                <dd>{{ page.props.auth.user.displayName }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    {{ t('identity.role') }}
                </dt>
                <dd>{{ t('identity.' + page.props.auth.user.role) }}</dd>
            </div>
        </dl>
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
