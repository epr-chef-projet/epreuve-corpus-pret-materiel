<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch, onScopeDispose, useId, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { LoadingButton } from '@/shared/ui/button';
import { show } from '@/routes/catalog';
import { useAccessStore } from '@/modules/identity-access/public/access';
import {
    usePendingOperations,
    type PendingProof,
} from '../composables/usePendingOperations';
import {
    CatalogReadError,
    closeOperation,
    readOperation,
} from '../requests/readEquipment';
import { isReceipt, type OperationType } from '../contracts/decoders';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
const props = withDefaults(
    defineProps<{
        receipt?: CommandReceipt | null;
        operationTypes?: readonly OperationType[];
        showStorageNotice?: boolean;
        resolveCreationKeys?: readonly string[];
    }>(),
    {
        receipt: undefined,
        operationTypes: undefined,
        showStorageNotice: true,
        resolveCreationKeys: () => [],
    },
);
const titleId = useId();
const panel = ref<HTMLElement>();
const emit = defineEmits<{
    applied: [key: string, receipt?: CommandReceipt];
    absent: [key: string];
    closed: [key: string];
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
const closedKeys = ref<string[]>([]);
const retryAfter = ref<Record<string, number | null>>({});
const controllers = new Set<AbortController>();
const canResolve = (proof: PendingProof) =>
    proof.operationType === 'catalog.equipment.create' &&
    proof.resourceId === null &&
    proof.expectedVersion === null &&
    props.resolveCreationKeys.includes(proof.operationKey);
async function check(proof: PendingProof, event: MouseEvent) {
    if (
        statuses.value[proof.operationKey] === 'checking' ||
        access.status !== 'active' ||
        !unresolved.value.some(
            (value) => value.operationKey === proof.operationKey,
        )
    )
        return;
    const resolving = canResolve(proof);
    if (resolving && access.stale) return;
    const trigger = event.currentTarget;
    const startedWithFocus = document.activeElement === trigger;
    const generation = access.generation;
    const abort = new AbortController();
    controllers.add(abort);
    const disown = access.own(() => abort.abort());
    statuses.value[proof.operationKey] = 'checking';
    retryAfter.value[proof.operationKey] = null;
    try {
        const result = resolving
            ? await closeOperation(proof.operationKey, abort.signal)
            : await readOperation(
                  proof.operationType,
                  proof.operationKey,
                  abort.signal,
                  proof.resourceId,
              );
        if (generation !== access.generation || abort.signal.aborted) return;
        const moveFocus =
            document.activeElement === trigger ||
            (startedWithFocus && document.activeElement === document.body);
        if (result.status === 'applied' && result.receipt) {
            statuses.value[proof.operationKey] = 'applied';
            appliedLinks.value[proof.operationKey] = result.receipt.resourceId;
            pending.finish(result.receipt);
            emit('applied', proof.operationKey, result.receipt);
        } else if (result.status === 'closed') {
            if (!pending.finishClosed(result.closure)) return;
            closedKeys.value = [
                ...new Set([...closedKeys.value, proof.operationKey]),
            ];
            emit('closed', proof.operationKey);
        } else {
            statuses.value[proof.operationKey] = 'absent';
            emit('absent', proof.operationKey);
        }
        if (moveFocus && result.status !== 'notEstablished') {
            await nextTick();
            if (
                generation !== access.generation ||
                abort.signal.aborted ||
                (document.activeElement !== document.body &&
                    document.activeElement !== trigger)
            )
                return;
            const outcome = panel.value?.querySelector<HTMLElement>(
                `[data-result-key="${proof.operationKey}"]`,
            );
            (
                outcome?.querySelector<HTMLAnchorElement>('a') ?? outcome
            )?.focus();
        }
    } catch (error) {
        if (generation === access.generation && !abort.signal.aborted) {
            statuses.value[proof.operationKey] = 'failed';
            if (error instanceof CatalogReadError)
                retryAfter.value[proof.operationKey] = error.retryAfter;
        }
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
        closedKeys.value = [];
        retryAfter.value = {};
    },
);
onScopeDispose(() => controllers.forEach((controller) => controller.abort()));
</script>

<template>
    <section
        v-if="
            unresolved.length ||
            Object.keys(appliedLinks).length ||
            closedKeys.length ||
            (showStorageNotice && pending.unavailable.value)
        "
        ref="panel"
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
                <p
                    v-if="
                        !canResolve(proof) ||
                        !['absent', 'failed'].includes(
                            statuses[proof.operationKey],
                        )
                    "
                >
                    {{
                        t(
                            canResolve(proof)
                                ? 'catalog.lostEntry'
                                : 'catalog.pending',
                        )
                    }}
                </p>
                <p
                    v-if="statuses[proof.operationKey] === 'absent'"
                    role="status"
                >
                    {{
                        t(
                            canResolve(proof)
                                ? 'catalog.resolveUnknown'
                                : 'catalog.notEstablished',
                        )
                    }}
                </p>
                <p
                    v-if="statuses[proof.operationKey] === 'failed'"
                    role="alert"
                >
                    {{
                        t(
                            canResolve(proof)
                                ? retryAfter[proof.operationKey]
                                    ? 'catalog.resolveLimited'
                                    : 'catalog.resolveFailed'
                                : 'catalog.verifyFailed',
                            { seconds: retryAfter[proof.operationKey] ?? 0 },
                        )
                    }}
                </p>
                <LoadingButton
                    type="button"
                    variant="outline"
                    :label="
                        t(
                            canResolve(proof)
                                ? 'catalog.resolve'
                                : 'catalog.verify',
                        )
                    "
                    :busy-label="
                        t(
                            canResolve(proof)
                                ? 'catalog.resolving'
                                : 'catalog.checking',
                        )
                    "
                    class="h-auto max-w-full py-2 whitespace-normal"
                    :busy="statuses[proof.operationKey] === 'checking'"
                    :disabled="
                        statuses[proof.operationKey] === 'checking' ||
                        access.status !== 'active' ||
                        (canResolve(proof) && access.stale)
                    "
                    @click="check(proof, $event)"
                />
            </li>
            <li
                v-for="(id, key) in appliedLinks"
                :key="key"
                role="status"
                tabindex="-1"
                :data-result-key="key"
            >
                {{ t('catalog.applied') }}
                <Link class="underline" :href="show(id)">{{
                    t('catalog.open')
                }}</Link>
            </li>
            <li
                v-for="key in closedKeys"
                :key="key"
                role="status"
                tabindex="-1"
                :data-result-key="key"
            >
                {{ t('catalog.closed') }}
            </li>
        </ul>
    </section>
</template>
