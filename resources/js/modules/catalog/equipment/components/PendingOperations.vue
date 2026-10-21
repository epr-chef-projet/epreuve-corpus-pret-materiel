<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch, onScopeDispose, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { LoadingButton } from '@/components/ui/button';
import { show } from '@/routes/catalog';
import { useAccessStore } from '@/modules/identity-access/public/access';
import {
    usePendingOperations,
    type PendingProof,
} from '../composables/usePendingOperations';
import { readOperation } from '../requests/readEquipment';
import { isReceipt, type OperationType } from '../contracts/decoders';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
const props = withDefaults(
    defineProps<{
        receipt?: CommandReceipt | null;
        operationTypes?: readonly OperationType[];
        showStorageNotice?: boolean;
    }>(),
    { receipt: undefined, operationTypes: undefined, showStorageNotice: true },
);
const titleId = useId();
const emit = defineEmits<{
    applied: [key: string, receipt?: CommandReceipt];
    absent: [key: string];
}>();
const { t } = useI18n();
const pending = usePendingOperations();
const unresolved = computed(() =>
    pending.unresolved.value.filter(
        (proof) =>
            (!props.operationTypes ||
                props.operationTypes.includes(proof.operationType)) &&
            (!isReceipt(props.receipt) ||
                props.receipt.operationKey !== proof.operationKey ||
                props.receipt.operationType !== proof.operationType),
    ),
);
const access = useAccessStore();
const statuses = ref<
    Record<string, 'checking' | 'absent' | 'failed' | 'applied'>
>({});
const appliedLinks = ref<Record<string, string>>({});
const controllers = new Set<AbortController>();
async function check(proof: PendingProof) {
    if (statuses.value[proof.operationKey] === 'checking') return;
    const generation = access.generation;
    const abort = new AbortController();
    controllers.add(abort);
    const disown = access.own(() => abort.abort());
    statuses.value[proof.operationKey] = 'checking';
    try {
        const result = await readOperation(
            proof.operationType,
            proof.operationKey,
            abort.signal,
            proof.resourceId,
        );
        if (generation !== access.generation || abort.signal.aborted) return;
        if (result.status === 'applied' && result.receipt) {
            statuses.value[proof.operationKey] = 'applied';
            appliedLinks.value[proof.operationKey] = result.receipt.resourceId;
            pending.finish(result.receipt);
            emit('applied', proof.operationKey, result.receipt);
        } else {
            statuses.value[proof.operationKey] = 'absent';
            emit('absent', proof.operationKey);
        }
    } catch {
        if (generation === access.generation && !abort.signal.aborted)
            statuses.value[proof.operationKey] = 'failed';
    } finally {
        disown();
        controllers.delete(abort);
    }
}
watch(
    () => access.generation,
    () => {
        statuses.value = {};
        appliedLinks.value = {};
    },
);
onScopeDispose(() => controllers.forEach((controller) => controller.abort()));
</script>

<template>
    <section
        v-if="
            unresolved.length ||
            Object.keys(appliedLinks).length ||
            (showStorageNotice && pending.unavailable.value)
        "
        class="flex flex-col gap-3 rounded-lg border p-4"
        :aria-labelledby="titleId"
    >
        <h2 :id="titleId" class="font-semibold">
            {{ t('catalog.pendingTitle') }}
        </h2>
        <p v-if="showStorageNotice && pending.unavailable.value" role="status">
            {{ t('catalog.storageLimit') }}
        </p>
        <ul class="flex flex-col gap-3">
            <li
                v-for="proof in unresolved"
                :key="proof.operationKey"
                class="flex flex-col items-start gap-2"
            >
                <p>{{ t('catalog.pending') }}</p>
                <p
                    v-if="statuses[proof.operationKey] === 'absent'"
                    role="status"
                >
                    {{ t('catalog.notEstablished') }}
                </p>
                <p
                    v-if="statuses[proof.operationKey] === 'failed'"
                    role="alert"
                >
                    {{ t('catalog.verifyFailed') }}
                </p>
                <LoadingButton
                    type="button"
                    variant="outline"
                    :label="t('catalog.verify')"
                    :busy-label="t('catalog.checking')"
                    :busy="statuses[proof.operationKey] === 'checking'"
                    :disabled="
                        statuses[proof.operationKey] === 'checking' ||
                        access.status !== 'active'
                    "
                    @click="check(proof)"
                />
            </li>
            <li v-for="(id, key) in appliedLinks" :key="key" role="status">
                {{ t('catalog.applied') }}
                <Link class="underline" :href="show(id)">{{
                    t('catalog.open')
                }}</Link>
            </li>
        </ul>
    </section>
</template>
