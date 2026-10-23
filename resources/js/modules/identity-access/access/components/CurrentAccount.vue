<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAccessStore } from '../stores/useAccessStore';
import { useI18n } from 'vue-i18n';
const page = usePage();
const access = useAccessStore();
const user = computed(() =>
    access.matchesContext(page.props.auth) ? access.context?.user : null,
);
const { t } = useI18n();
</script>

<template>
    <dl v-if="user" class="flex min-w-0 flex-wrap gap-x-5 gap-y-2 text-sm">
        <div>
            <dt class="text-muted-foreground">{{ t('identity.workshop') }}</dt>
            <dd class="break-words">{{ user.workshopName }}</dd>
        </div>
        <div>
            <dt class="text-muted-foreground">{{ t('identity.account') }}</dt>
            <dd class="break-words">{{ user.displayName }}</dd>
        </div>
        <div>
            <dt class="text-muted-foreground">{{ t('identity.role') }}</dt>
            <dd>{{ t('identity.' + user.role) }}</dd>
        </div>
    </dl>
</template>
