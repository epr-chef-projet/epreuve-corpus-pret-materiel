import { useForm, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onScopeDispose,
    ref,
    shallowRef,
    watch,
    type Ref,
} from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { useAccessStore } from '@/modules/identity-access/public/access';
import { store, update } from '@/routes/catalog';
import { fieldError } from '@/shared/i18n/fieldError';
import type { EquipmentData } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
import { isReceipt } from '../contracts/decoders';
import { usePendingOperations } from './usePendingOperations';

type Body = Record<string, string | number | null>;
export function useEquipmentForm(
    equipment: Ref<EquipmentData | undefined>,
    receipt: Ref<CommandReceipt | null | undefined>,
) {
    const access = useAccessStore();
    const page = usePage();
    const composer = useI18n();
    const pending = usePendingOperations();
    const generation = access.generation;
    const pendingForTarget = computed(() =>
        pending.proofs.value.some((proof) =>
            equipment.value
                ? proof.resourceId === equipment.value.equipmentId
                : proof.operationType === 'catalog.equipment.create',
        ),
    );
    const form = useForm({
        name: equipment.value?.name ?? '',
        category: equipment.value?.category ?? '',
        description: equipment.value?.description ?? '',
        totalQuantity: String(equipment.value?.totalQuantity ?? 0),
        expectedVersion: equipment.value?.equipmentVersion ?? 1,
    });
    const saved = ref(false);
    const preparing = ref(false);
    const preparationFailed = ref(false);
    const submitting = computed(() => form.processing || preparing.value);
    const sendingFrom = ref<'submit' | 'resume'>('submit');
    const resumable = ref(false);
    let disposed = false;
    const intention = shallowRef<{ key: string; body: Body } | null>(null);
    let consumedReceipt: string | null = null;
    const current = () => !disposed && generation === access.generation;
    const divergence = computed(
        () =>
            current() &&
            !intention.value &&
            equipment.value !== undefined &&
            equipment.value.equipmentVersion > form.expectedVersion,
    );
    const fields = () => ({
        name: form.name,
        category: form.category,
        description: form.description,
        ...(equipment.value
            ? { expectedVersion: form.expectedVersion }
            : { totalQuantity: form.totalQuantity }),
    });
    function loadCurrent() {
        const value = equipment.value;
        if (!value || intention.value) return;
        form.name = value.name;
        form.category = value.category;
        form.description = value.description ?? '';
        form.expectedVersion = value.equipmentVersion;
        form.totalQuantity = String(value.totalQuantity);
        form.defaults();
        form.clearErrors();
    }
    function consumeReceipt(value: unknown) {
        if (!current() || !pending.ready.value || !isReceipt(value)) return;
        if (
            ![
                'catalog.equipment.create',
                'catalog.equipment.update_text',
            ].includes(value.operationType)
        )
            return;
        if (value.operationKey === consumedReceipt) return;
        if (equipment.value && value.resourceId !== equipment.value.equipmentId)
            return;
        if (intention.value && intention.value.key !== value.operationKey)
            return;
        const sent = intention.value;
        const unchanged = sent
            ? Object.entries(fields()).every(
                  ([key, field]) => sent.body[key] === field,
              )
            : !form.isDirty;
        pending.finish(value);
        consumedReceipt = value.operationKey;
        saved.value = true;
        toast.success(composer.t('catalog.saved'), {
            id: 'catalog-save-' + value.operationKey,
            duration: 5000,
        });
        resumable.value = false;
        intention.value = null;
        if (unchanged) {
            if (
                sent &&
                equipment.value &&
                equipment.value.equipmentVersion !== value.resultVersion
            ) {
                form.expectedVersion = value.resultVersion;
                form.defaults();
            } else loadCurrent();
        }
    }
    watch([receipt, pending.ready], ([value]) => consumeReceipt(value), {
        immediate: true,
    });
    watch(equipment, (value, previous) => {
        if (
            !current() ||
            !value ||
            value.equipmentVersion === previous?.equipmentVersion
        )
            return;
        if (
            !form.isDirty &&
            !intention.value &&
            value.equipmentVersion >= form.expectedVersion &&
            (form.expectedVersion === previous?.equipmentVersion ||
                form.expectedVersion === value.equipmentVersion)
        )
            loadCurrent();
    });
    function send() {
        if (!current() || !intention.value) return;
        const key = intention.value.key;
        if (access.status !== 'active' || access.stale) {
            pending.endSending(key);
            return;
        }
        const body = { ...intention.value.body };
        if (consumedReceipt) toast.dismiss('catalog-save-' + consumedReceipt);
        pending.markSending(key);
        resumable.value = false;
        saved.value = false;
        form.transform(() => body);
        const options = {
            headers: {
                'X-EPR-Operation-Key': key,
                'X-EPR-Operation-Type': equipment.value
                    ? 'catalog.equipment.update_text'
                    : 'catalog.equipment.create',
            },
            preserveScroll: true,
            onSuccess: (response: ReturnType<typeof usePage>) => {
                if (current()) consumeReceipt(response.props.commandReceipt);
            },
            onError: () => {
                if (!current()) return;
                pending.reject(key);
                intention.value = null;
            },
            onFinish: () => {
                pending.endSending(key);
                if (current() && form.hasErrors)
                    void nextTick(() => {
                        if (!current()) return;
                        const field = Object.keys(form.errors)[0];
                        (
                            document.getElementById('equipment-' + field) ??
                            document.getElementById('equipment-form-feedback')
                        )?.focus();
                    });
            },
        };
        if (equipment.value)
            form.patch(update.url(equipment.value.equipmentId), options);
        else form.post(store.url(), options);
    }
    async function submit() {
        if (
            !current() ||
            preparing.value ||
            form.processing ||
            intention.value ||
            pendingForTarget.value ||
            (saved.value && !equipment.value) ||
            !pending.ready.value ||
            pending.proofs.value.length >= 20 ||
            access.status !== 'active' ||
            access.stale
        )
            return;
        preparing.value = true;
        sendingFrom.value = 'submit';
        preparationFailed.value = false;
        try {
            const body: Body = {
                ...fields(),
                operationKey: crypto.randomUUID(),
            };
            const proof = await pending.begin(
                equipment.value
                    ? 'catalog.equipment.update_text'
                    : 'catalog.equipment.create',
                body,
                equipment.value?.equipmentId ?? null,
                equipment.value ? form.expectedVersion : null,
            );
            if (!current()) return;
            intention.value = { key: proof.operationKey, body };
            send();
        } catch {
            if (current()) {
                if (intention.value) pending.endSending(intention.value.key);
                preparationFailed.value = intention.value === null;
            }
        } finally {
            if (current()) preparing.value = false;
        }
    }
    function absent(key: string) {
        if (intention.value?.key === key) resumable.value = true;
    }
    function applied(key: string, receipt?: CommandReceipt) {
        if (intention.value?.key === key) {
            if (isReceipt(receipt) && receipt.operationKey === key) {
                consumeReceipt(receipt);
                return;
            }
            intention.value = null;
            saved.value = true;
        }
    }
    function resume() {
        if (resumable.value && !submitting.value) {
            sendingFrom.value = 'resume';
            send();
        }
    }
    const release = access.own(() => {
        intention.value = null;
        form.resetAndClearErrors();
    });
    onScopeDispose(() => {
        disposed = true;
        release();
        intention.value = null;
        form.resetAndClearErrors();
    });
    const error = (name: keyof typeof form.errors) =>
        fieldError(
            page.props.validationPresentation,
            String(name),
            form.errors[name],
            composer,
        );
    const globalError = computed(() =>
        'catalog' in form.errors && typeof form.errors.catalog === 'string'
            ? fieldError(
                  page.props.validationPresentation,
                  'catalog',
                  form.errors.catalog,
                  composer,
              )
            : '',
    );
    return {
        form,
        saved,
        preparing,
        preparationFailed,
        submitting,
        sendingFrom,
        resumable,
        divergence,
        error,
        globalError,
        pending,
        submit,
        resume,
        absent,
        applied,
        loadCurrent,
        pendingForTarget,
        waiting: computed(() => intention.value !== null),
    };
}
