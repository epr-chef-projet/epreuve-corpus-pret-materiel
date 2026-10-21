<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { LoadingButton } from '@/components/ui/button';
import { manageCatalog } from '@/routes';
import type { EquipmentData } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
import type { IssueData } from '@/types/generated/types/App/Shared/Errors';
import { useCatalogProjection } from '../composables/useCatalogProjection';
import { readEquipment } from '../requests/readEquipment';
import EquipmentForm from './EquipmentForm.vue';
import EquipmentReadStatus from './EquipmentReadStatus.vue';
const props = defineProps<{
    initialEquipment: EquipmentData;
    receipt?: CommandReceipt | null;
    issue?: IssueData | null;
}>();
const { t } = useI18n();
const projection = useCatalogProjection(
    toRef(props, 'initialEquipment'),
    () => props.initialEquipment.equipmentId,
    (signal) => readEquipment(props.initialEquipment.equipmentId, signal),
);
</script>

<template>
    <section class="flex flex-col gap-5 p-4 sm:p-6">
        <Head :title="t('catalog.details')" />
        <div class="relative self-start pr-7">
            <h1 class="text-2xl font-semibold">{{ t('catalog.details') }}</h1>
            <EquipmentReadStatus
                :verifying="
                    projection.verifying.value &&
                    !projection.manualRefreshing.value
                "
            />
        </div>
        <Link class="underline" :href="manageCatalog()">{{
            t('catalog.back')
        }}</Link>
        <LoadingButton
            variant="outline"
            class="self-start"
            :label="t('catalog.retryRead')"
            :busy-label="t('catalog.loading')"
            :busy="projection.manualRefreshing.value"
            :disabled="projection.manualRefreshing.value"
            @click="projection.refresh"
        />
        <div v-if="projection.failed.value" role="alert">
            <p>{{ t('catalog.readFailed') }}</p>
        </div>
        <p
            v-else-if="projection.expired.value"
            role="status"
            class="text-sm text-muted-foreground"
        >
            {{ t('catalog.stale') }}
        </p>
        <section
            class="grid gap-4 rounded-lg border p-4 sm:grid-cols-[10rem_1fr]"
            :aria-label="t('catalog.currentValues')"
        >
            <div
                class="grid min-h-28 place-items-center rounded bg-muted text-sm text-muted-foreground"
            >
                {{ t('catalog.noImage') }}
            </div>
            <div class="flex min-w-0 flex-col gap-2">
                <h2 class="text-xl font-semibold break-words">
                    {{ projection.data.value.name }}
                </h2>
                <p class="break-words">{{ projection.data.value.category }}</p>
                <p class="break-words whitespace-pre-wrap">
                    {{ projection.data.value.description }}
                </p>
                <p>
                    {{ t('catalog.total') }} :
                    {{
                        projection.data.value.totalQuantity.toLocaleString(
                            $i18n.locale,
                        )
                    }}
                </p>
                <p>
                    {{
                        t(
                            projection.data.value.status === 'active'
                                ? 'catalog.active'
                                : 'catalog.archived',
                        )
                    }}
                </p>
            </div>
        </section>
        <p class="text-sm text-muted-foreground">
            {{ t('catalog.totalNotice') }}
        </p>
        <p class="text-sm text-muted-foreground">{{ t('catalog.partial') }}</p>
        <EquipmentForm
            :key="initialEquipment.equipmentId"
            :equipment="projection.data.value"
            :receipt="receipt"
            :issue="issue"
            @verified="projection.recheck"
        />
    </section>
</template>
