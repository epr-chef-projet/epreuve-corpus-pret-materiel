import { useForm, usePage } from '@inertiajs/vue3';
import {
    computed,
    onScopeDispose,
    ref,
    shallowRef,
    watch,
    type Ref,
} from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { useAccessStore } from '@/modules/identity-access/public/access';
import { archive, reactivate } from '@/routes/catalog';
import { fieldError } from '@/shared/i18n/fieldError';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
import type { EquipmentData } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import { isReceipt } from '../contracts/decoders';
import { usePendingOperations } from './usePendingOperations';

type StateOperation =
    'catalog.equipment.archive' | 'catalog.equipment.reactivate';
type StateBody = { operationKey: string; expectedVersion: number };
export function useEquipmentState(
    equipment: Ref<EquipmentData>,
    receipt: Ref<CommandReceipt | null | undefined>,
    onApplied: () => void,
) {
    const access = useAccessStore();
    const generation = access.generation;
    const composer = useI18n();
    const page = usePage();
    const pending = usePendingOperations();
    const form = useForm({
        expectedVersion: equipment.value.equipmentVersion,
        operationKey: '',
    });
    const preparing = ref(false);
    const preparationFailed = ref(false);
    const resumable = ref(false);
    const sendingFrom = ref<'submit' | 'resume'>('submit');
    const intention = shallowRef<{
        operationType: StateOperation;
        body: StateBody;
    } | null>(null);
    let disposed = false;
    let consumed: string | null = null;
    const current = () => !disposed && access.generation === generation;
    const busy = computed(() => preparing.value || form.processing);
    const blocked = computed(
        () =>
            busy.value ||
            intention.value !== null ||
            !pending.ready.value ||
            pending.proofs.value.length >= 20 ||
            pending.proofs.value.some(
                (proof) => proof.resourceId === equipment.value.equipmentId,
            ) ||
            access.status !== 'active' ||
            access.stale,
    );
    const operationType = computed<StateOperation>(
        () =>
            intention.value?.operationType ??
            (equipment.value.status === 'active'
                ? 'catalog.equipment.archive'
                : 'catalog.equipment.reactivate'),
    );
    function consume(value: unknown) {
        if (
            !current() ||
            !pending.ready.value ||
            !isReceipt(value) ||
            ![
                'catalog.equipment.archive',
                'catalog.equipment.reactivate',
            ].includes(value.operationType) ||
            value.resourceId !== equipment.value.equipmentId ||
            value.operationKey === consumed ||
            (intention.value &&
                (intention.value.body.operationKey !== value.operationKey ||
                    intention.value.operationType !== value.operationType))
        )
            return;
        pending.finish(value);
        consumed = value.operationKey;
        intention.value = null;
        resumable.value = false;
        form.clearErrors();
        toast.success(
            composer.t(
                value.operationType === 'catalog.equipment.archive'
                    ? 'catalog.archiveDone'
                    : 'catalog.reactivateDone',
            ),
            { id: 'catalog-state-' + value.operationKey, duration: 5000 },
        );
        onApplied();
    }
    watch([receipt, pending.ready], ([value]) => consume(value), {
        immediate: true,
    });
    function send() {
        const sent = intention.value;
        if (!current() || !sent || busy.value) return;
        if (access.status !== 'active' || access.stale) {
            pending.endSending(sent.body.operationKey);
            return;
        }
        if (consumed) toast.dismiss('catalog-state-' + consumed);
        pending.markSending(sent.body.operationKey);
        resumable.value = false;
        form.clearErrors();
        form.transform(() => ({ ...sent.body }));
        form.post(
            (sent.operationType === 'catalog.equipment.archive'
                ? archive
                : reactivate
            ).url(equipment.value.equipmentId),
            {
                preserveState: true,
                preserveScroll: true,
                headers: {
                    'X-EPR-Operation-Key': sent.body.operationKey,
                    'X-EPR-Operation-Type': sent.operationType,
                },
                onSuccess: (response) => consume(response.props.commandReceipt),
                onError: () => {
                    if (!current()) return;
                    pending.reject(sent.body.operationKey);
                    intention.value = null;
                },
                onFinish: () => pending.endSending(sent.body.operationKey),
            },
        );
    }
    async function submit(type: StateOperation, expectedVersion: number) {
        if (blocked.value || !current()) return;
        preparing.value = true;
        preparationFailed.value = false;
        sendingFrom.value = 'submit';
        try {
            const body: StateBody = {
                operationKey: crypto.randomUUID(),
                expectedVersion,
            };
            await pending.begin(
                type,
                body,
                equipment.value.equipmentId,
                body.expectedVersion,
            );
            if (!current()) return;
            intention.value = { operationType: type, body };
        } catch {
            if (current()) preparationFailed.value = true;
        } finally {
            if (current()) preparing.value = false;
        }
        if (current() && intention.value) send();
    }
    function resume() {
        if (!resumable.value || busy.value) return;
        sendingFrom.value = 'resume';
        send();
    }
    function absent(key: string) {
        if (intention.value?.body.operationKey === key) resumable.value = true;
    }
    function applied(key: string, value?: CommandReceipt) {
        if (value?.operationKey === key) consume(value);
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
    const error = computed(() => {
        const messages: unknown = form.errors;
        return typeof messages === 'object' &&
            messages !== null &&
            'catalog' in messages &&
            typeof messages.catalog === 'string'
            ? fieldError(
                  page.props.validationPresentation,
                  'catalog',
                  messages.catalog,
                  composer,
              )
            : '';
    });
    return {
        busy,
        blocked,
        operationType,
        preparing,
        preparationFailed,
        resumable,
        sendingFrom,
        error,
        submit,
        resume,
        absent,
        applied,
    };
}
