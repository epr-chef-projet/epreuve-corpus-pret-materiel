<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, watch, onScopeDispose } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/catalog';
import { useAccessStore } from '@/modules/identity-access/public/access';
import {
    usePendingOperations,
    type PendingProof,
} from '../composables/usePendingOperations';
import { readOperation } from '../requests/readEquipment';
const emit = defineEmits<{ applied: [key: string]; absent: [key: string] }>();
const { t } = useI18n();
const pending = usePendingOperations();
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
        );
        if (generation !== access.generation || abort.signal.aborted) return;
        if (result.status === 'applied' && result.receipt) {
            statuses.value[proof.operationKey] = 'applied';
            appliedLinks.value[proof.operationKey] = result.receipt.resourceId;
            pending.finish(result.receipt);
            emit('applied', proof.operationKey);
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
            pending.proofs.value.length ||
            Object.keys(appliedLinks).length ||
            pending.unavailable.value
        "
        class="flex flex-col gap-3 rounded-lg border p-4"
        aria-labelledby="pending-title"
    >
        <h2 id="pending-title" class="font-semibold">
            {{ t('catalog.pendingTitle') }}
        </h2>
        <p v-if="pending.unavailable.value" role="status">
            {{ t('catalog.storageLimit') }}
        </p>
        <ul class="flex flex-col gap-3">
            <li
                v-for="proof in pending.proofs.value"
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
                <Button
                    type="button"
                    variant="outline"
                    :disabled="
                        statuses[proof.operationKey] === 'checking' ||
                        access.status !== 'active'
                    "
                    @click="check(proof)"
                    >{{
                        t(
                            statuses[proof.operationKey] === 'checking'
                                ? 'catalog.checking'
                                : 'catalog.verify',
                        )
                    }}</Button
                >
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
