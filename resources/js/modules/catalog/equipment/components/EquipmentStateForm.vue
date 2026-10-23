<script setup lang="ts">
import { nextTick, ref, toRef, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button, LoadingButton } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
import type { EquipmentData } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import { useEquipmentState } from '../composables/useEquipmentState';
import PendingOperations from './PendingOperations.vue';
const props = defineProps<{
    equipment: EquipmentData;
    receipt?: CommandReceipt | null;
}>();
const emit = defineEmits<{ verified: [] }>();
const { t } = useI18n();
const open = ref(false);
let archiveVersion = props.equipment.equipmentVersion;
watch(
    open,
    (shown) => {
        if (shown) archiveVersion = props.equipment.equipmentVersion;
    },
    { flush: 'sync' },
);
const heading = useTemplateRef<HTMLHeadingElement>('heading');
const state = useEquipmentState(
    toRef(props, 'equipment'),
    toRef(props, 'receipt'),
    () => emit('verified'),
);
watch(state.busy, (busy, previous) => {
    if (
        !busy &&
        previous &&
        (document.activeElement === heading.value ||
            document.activeElement === document.body)
    ) {
        void nextTick(() =>
            document.getElementById('equipment-state-action')?.focus(),
        );
    }
});
function confirm() {
    open.value = false;
    void state.submit('catalog.equipment.archive', archiveVersion);
}
function restoreFocus(event: Event) {
    event.preventDefault();
    void nextTick(() => {
        const button = document.getElementById('equipment-state-action');
        if (button instanceof HTMLButtonElement && !button.disabled)
            button.focus();
        else heading.value?.focus();
    });
}
</script>

<template>
    <section
        class="flex flex-col items-start gap-3 rounded-lg border p-4"
        aria-labelledby="equipment-state-title"
    >
        <h2
            id="equipment-state-title"
            ref="heading"
            class="text-xl font-semibold"
            tabindex="-1"
        >
            {{ t('catalog.stateActions') }}
        </h2>
        <Dialog v-model:open="open">
            <DialogTrigger
                v-if="state.operationType.value === 'catalog.equipment.archive'"
                as-child
            >
                <LoadingButton
                    id="equipment-state-action"
                    type="button"
                    variant="outline"
                    :label="t('catalog.archive')"
                    :busy-label="t('catalog.stateSaving')"
                    :busy="
                        state.busy.value && state.sendingFrom.value === 'submit'
                    "
                    :disabled="state.blocked.value"
                />
            </DialogTrigger>
            <LoadingButton
                v-else
                id="equipment-state-action"
                type="button"
                variant="outline"
                :label="t('catalog.reactivate')"
                :busy-label="t('catalog.stateSaving')"
                :busy="state.busy.value && state.sendingFrom.value === 'submit'"
                :disabled="state.blocked.value"
                @click="
                    state.submit(
                        'catalog.equipment.reactivate',
                        equipment.equipmentVersion,
                    )
                "
            />
            <DialogContent
                :show-close-button="false"
                class="motion-reduce:animate-none"
                @close-auto-focus="restoreFocus"
            >
                <DialogHeader>
                    <DialogTitle>{{ t('catalog.archiveTitle') }}</DialogTitle>
                    <DialogDescription>{{
                        t('catalog.archiveDescription')
                    }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-3">
                    <DialogClose as-child
                        ><Button type="button" variant="outline">{{
                            t('catalog.cancel')
                        }}</Button></DialogClose
                    >
                    <Button
                        type="button"
                        :disabled="state.blocked.value"
                        @click="confirm"
                        >{{ t('catalog.archiveConfirm') }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <p v-if="state.error.value" role="alert">{{ state.error.value }}</p>
        <p v-if="state.preparationFailed.value" role="alert">
            {{ t('catalog.preparationFailed') }}
        </p>
        <LoadingButton
            v-if="
                state.resumable.value ||
                (state.busy.value && state.sendingFrom.value === 'resume')
            "
            type="button"
            variant="outline"
            class="h-auto max-w-full py-2 whitespace-normal"
            :label="t('catalog.resumeState')"
            :busy-label="t('catalog.stateSaving')"
            :busy="state.busy.value"
            :disabled="state.busy.value"
            @click="state.resume"
        />
        <PendingOperations
            :show-storage-notice="false"
            :receipt="receipt"
            :operation-types="[
                'catalog.equipment.archive',
                'catalog.equipment.reactivate',
            ]"
            @absent="state.absent"
            @applied="state.applied"
        />
    </section>
</template>
