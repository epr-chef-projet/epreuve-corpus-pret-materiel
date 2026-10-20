<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { explainIssue } from '@/shared/i18n/issue';
import type { EquipmentData } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
import type { IssueData } from '@/types/generated/types/App/Shared/Errors';
import { useEquipmentForm } from '../composables/useEquipmentForm';
import PendingOperations from './PendingOperations.vue';
const props = defineProps<{
    equipment?: EquipmentData;
    receipt?: CommandReceipt | null;
    issue?: IssueData | null;
}>();
const emit = defineEmits<{ verified: [] }>();
const composer = useI18n();
const { t } = composer;
const state = useEquipmentForm(
    toRef(props, 'equipment'),
    toRef(props, 'receipt'),
);
const { form } = state;
function applied(key: string) {
    state.applied(key);
    emit('verified');
}
const explanation = computed(() =>
    props.issue ? explainIssue(props.issue, composer) : null,
);
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 v-if="equipment" class="text-xl font-semibold">
            {{ t('catalog.edit') }}
        </h2>
        <p>{{ t('catalog.required') }}</p>
        <p v-if="state.saved.value" role="status">
            {{ t(receipt?.replayed ? 'catalog.replayed' : 'catalog.saved') }}
        </p>
        <div v-if="explanation" role="alert">
            <p>{{ explanation.message }}</p>
            <p>{{ explanation.next }}</p>
            <p v-if="explanation.correlationId">
                {{ t('common.correlation') }} : {{ explanation.correlationId }}
            </p>
        </div>
        <p
            v-if="state.globalError.value"
            id="equipment-catalog"
            role="alert"
            tabindex="-1"
        >
            {{ state.globalError.value }}
        </p>
        <div
            v-if="state.divergence.value"
            role="status"
            class="rounded-lg border p-4"
        >
            <p>{{ t('catalog.conflict') }}</p>
            <p>{{ t('catalog.dirtyNotice') }}</p>
            <Button
                type="button"
                variant="outline"
                :disabled="state.waiting.value"
                @click="state.loadCurrent"
                >{{ t('catalog.loadCurrent') }}</Button
            >
        </div>
        <p
            v-if="form.hasErrors"
            id="equipment-form-feedback"
            role="alert"
            tabindex="-1"
        >
            {{ t('common.validationFallback') }}
        </p>
        <form
            class="flex max-w-2xl flex-col gap-5"
            @submit.prevent="state.submit"
        >
            <fieldset
                class="flex min-w-0 flex-col gap-5"
                :disabled="form.processing || state.preparing.value"
            >
                <div class="grid gap-2">
                    <Label for="equipment-name">{{ t('catalog.name') }} *</Label
                    ><Input
                        id="equipment-name"
                        v-model="form.name"
                        name="name"
                        required
                        maxlength="120"
                        aria-describedby="equipment-name-error"
                        :aria-invalid="Boolean(form.errors.name)"
                    /><InputError
                        id="equipment-name-error"
                        :message="state.error('name')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="equipment-category"
                        >{{ t('catalog.category') }} *</Label
                    ><Input
                        id="equipment-category"
                        v-model="form.category"
                        name="category"
                        required
                        maxlength="80"
                        aria-describedby="equipment-category-error"
                        :aria-invalid="Boolean(form.errors.category)"
                    /><InputError
                        id="equipment-category-error"
                        :message="state.error('category')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="equipment-description">{{
                        t('catalog.description')
                    }}</Label
                    ><textarea
                        id="equipment-description"
                        v-model="form.description"
                        name="description"
                        maxlength="2000"
                        rows="5"
                        class="min-h-28 w-full rounded-md border bg-transparent px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                        aria-describedby="equipment-description-error"
                        :aria-invalid="Boolean(form.errors.description)"
                    /><InputError
                        id="equipment-description-error"
                        :message="state.error('description')"
                    />
                </div>
                <div v-if="!equipment" class="grid gap-2">
                    <Label for="equipment-totalQuantity"
                        >{{ t('catalog.initialTotal') }} *</Label
                    ><Input
                        id="equipment-totalQuantity"
                        v-model="form.totalQuantity"
                        name="totalQuantity"
                        inputmode="numeric"
                        required
                        aria-describedby="equipment-total-error"
                        :aria-invalid="Boolean(form.errors.totalQuantity)"
                    /><InputError
                        id="equipment-total-error"
                        :message="state.error('totalQuantity')"
                    />
                </div>
                <Button
                    class="self-start"
                    type="submit"
                    :disabled="
                        state.waiting.value ||
                        state.pendingForTarget.value ||
                        (state.saved.value && !equipment) ||
                        !state.pending.ready.value ||
                        state.pending.proofs.value.length >= 20
                    "
                    >{{
                        t(
                            form.processing || state.preparing.value
                                ? 'catalog.saving'
                                : equipment
                                  ? 'catalog.save'
                                  : 'catalog.createSubmit',
                        )
                    }}</Button
                >
            </fieldset>
        </form>
        <p v-if="state.preparationFailed.value" role="alert">
            {{ t('catalog.preparationFailed') }}
        </p>
        <p v-if="state.pending.proofs.value.length >= 20" role="status">
            {{ t('catalog.pendingLimit') }}
        </p>
        <p v-if="state.uncertain.value" role="status">
            {{ t('catalog.pending') }}
        </p>
        <PendingOperations @absent="state.absent" @applied="applied" />
        <Button
            v-if="state.resumable.value"
            type="button"
            variant="outline"
            class="self-start"
            @click="state.resume"
            >{{ t('catalog.resume') }}</Button
        >
        <p v-if="state.pending.proofs.value.length && !state.waiting.value">
            {{ t('catalog.cannotResume') }}
        </p>
    </section>
</template>
