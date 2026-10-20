<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
const page = usePage();
const { t } = useI18n();
</script>

<template>
    <dl
        v-if="page.props.auth.user"
        class="flex min-w-0 flex-wrap gap-x-5 gap-y-2 text-sm"
    >
        <div>
            <dt class="text-muted-foreground">{{ t('identity.workshop') }}</dt>
            <dd class="break-words">{{ page.props.auth.user.workshopName }}</dd>
        </div>
        <div>
            <dt class="text-muted-foreground">{{ t('identity.account') }}</dt>
            <dd class="break-words">{{ page.props.auth.user.displayName }}</dd>
        </div>
        <div>
            <dt class="text-muted-foreground">{{ t('identity.role') }}</dt>
            <dd>{{ t('identity.' + page.props.auth.user.role) }}</dd>
        </div>
    </dl>
</template>
