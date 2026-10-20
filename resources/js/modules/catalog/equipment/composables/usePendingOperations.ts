import { onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAccessStore } from '@/modules/identity-access/public/access';
import {
    isReceipt,
    operationTypes,
    record,
    uuid,
    type OperationType,
} from '../contracts/decoders';

export type PendingProof = {
    scope: string;
    operationKey: string;
    operationType: OperationType;
    resourceId: string | null;
    expectedVersion: number | null;
    contentHash: string;
};
const storageKey = 'epr.pendingCatalogOperations';
const changed = new EventTarget();
const memoryByScope = new Map<string, PendingProof[]>();
let storageUnavailable = false;
function clearPending() {
    memoryByScope.clear();
    storageUnavailable = false;
    try {
        sessionStorage.removeItem(storageKey);
    } catch {
        /* In-memory proofs are still cleared. */
    }
    changed.dispatchEvent(new Event('changed'));
}
export function installCatalogOperationCleanup() {
    const access = useAccessStore();
    return watch(
        () => [access.generation, access.status] as const,
        ([generation, status], previous) => {
            if (
                (previous && generation !== previous[0]) ||
                status === 'guest' ||
                status === 'signingOut'
            )
                clearPending();
        },
        { flush: 'sync' },
    );
}
export function usePendingOperations() {
    const access = useAccessStore();
    const generation = access.generation;
    const proofs = shallowRef<PendingProof[]>([]);
    const unavailable = ref(false);
    const ready = ref(false);
    let scope = '';
    let disposed = false;

    const current = () => !disposed && generation === access.generation;
    function load() {
        if (!current() || !scope) return;
        if (storageUnavailable) {
            unavailable.value = true;
            proofs.value = [...(memoryByScope.get(scope) ?? [])];
            return;
        }
        try {
            const values: unknown = JSON.parse(
                sessionStorage.getItem(storageKey) ?? '[]',
            );
            if (!Array.isArray(values) || values.length > 20)
                throw new Error('Invalid pending proofs');
            proofs.value = values.filter(
                (value): value is PendingProof =>
                    record(value) &&
                    Object.keys(value).length === 6 &&
                    value.scope === scope &&
                    uuid(value.operationKey) &&
                    operationTypes.some(
                        (type) => type === value.operationType,
                    ) &&
                    (value.resourceId === null || uuid(value.resourceId)) &&
                    (value.expectedVersion === null ||
                        (Number.isSafeInteger(value.expectedVersion) &&
                            Number(value.expectedVersion) > 0 &&
                            Number(value.expectedVersion) <= 2147483647)) &&
                    typeof value.contentHash === 'string' &&
                    /^[0-9a-f]{64}$/.test(value.contentHash),
            );
            memoryByScope.set(scope, [...proofs.value]);
        } catch {
            storageUnavailable = true;
            unavailable.value = true;
            proofs.value = [...(memoryByScope.get(scope) ?? [])];
        }
    }
    function persist(values: PendingProof[]) {
        memoryByScope.set(scope, values);
        proofs.value = values;
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(values));
        } catch {
            storageUnavailable = true;
            unavailable.value = true;
        }
        changed.dispatchEvent(new Event('changed'));
    }
    async function initialize() {
        const context = access.context;
        if (!context?.user || !context.broadcastToken) return;
        const digest = await crypto.subtle.digest(
            'SHA-256',
            new TextEncoder().encode(
                JSON.stringify([
                    context.user.id,
                    context.broadcastToken,
                    context.authGeneration,
                ]),
            ),
        );
        if (!current()) return;
        scope = Array.from(new Uint8Array(digest), (byte) =>
            byte.toString(16).padStart(2, '0'),
        ).join('');
        load();
        ready.value = true;
    }
    async function begin(
        operationType: OperationType,
        body: Record<string, unknown>,
        resourceId: string | null,
        expectedVersion: number | null,
    ): Promise<PendingProof> {
        if (!ready.value || proofs.value.length >= 20)
            throw new Error('Resolve existing pending saves first');
        const digest = await crypto.subtle.digest(
            'SHA-256',
            new TextEncoder().encode(JSON.stringify(body)),
        );
        if (!current()) throw new DOMException('Context changed', 'AbortError');
        if (proofs.value.length >= 20 || !uuid(body.operationKey))
            throw new Error('Invalid or excessive pending save');
        const proof: PendingProof = {
            scope,
            operationKey: String(body.operationKey),
            operationType,
            resourceId,
            expectedVersion,
            contentHash: Array.from(new Uint8Array(digest), (byte) =>
                byte.toString(16).padStart(2, '0'),
            ).join(''),
        };
        persist([
            ...proofs.value.filter(
                (value) => value.operationKey !== proof.operationKey,
            ),
            proof,
        ]);
        return proof;
    }
    function finish(receipt: unknown) {
        if (!current() || !isReceipt(receipt)) return;
        persist(
            proofs.value.filter(
                (value) =>
                    value.operationKey !== receipt.operationKey ||
                    value.operationType !== receipt.operationType,
            ),
        );
    }
    function reject(operationKey: string) {
        if (current())
            persist(
                proofs.value.filter(
                    (value) => value.operationKey !== operationKey,
                ),
            );
    }
    const clear = () => {
        memoryByScope.delete(scope);
        proofs.value = [];
        try {
            sessionStorage.removeItem(storageKey);
        } catch {
            unavailable.value = true;
        }
    };
    const disown = access.own(clear);
    changed.addEventListener('changed', load);
    onScopeDispose(() => {
        disposed = true;
        disown();
        changed.removeEventListener('changed', load);
    });
    void initialize();
    return { proofs, unavailable, ready, begin, finish, reject };
}
