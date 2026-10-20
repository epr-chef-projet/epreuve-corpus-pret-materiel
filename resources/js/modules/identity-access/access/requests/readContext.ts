import { http } from '@inertiajs/core';
import { context } from '@/routes/access';
import { withRecoverySlot } from '@/shared/network/recoveryBudget';
import type { SessionContextData } from '@/types/generated/types/App/Modules/IdentityAccess/Access/Data';

const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
export function isSessionContext(value: unknown): value is SessionContextData {
    if (!record(value) || !['fr', 'en'].includes(String(value.locale)))
        return false;
    if (value.user === null) {
        return (
            value.broadcastToken === null &&
            value.authGeneration === null &&
            value.expiresAt === null
        );
    }
    const user = value.user;
    return (
        record(user) &&
        Object.keys(user).length === 6 &&
        typeof user.id === 'string' &&
        uuid.test(user.id) &&
        typeof user.workshopId === 'string' &&
        uuid.test(user.workshopId) &&
        typeof user.login === 'string' &&
        user.login.length <= 190 &&
        typeof user.displayName === 'string' &&
        user.displayName.length <= 190 &&
        typeof user.workshopName === 'string' &&
        user.workshopName.length <= 190 &&
        ['borrower', 'staff', 'manager'].includes(String(user.role)) &&
        typeof value.broadcastToken === 'string' &&
        /^[0-9a-f]{64}$/.test(value.broadcastToken) &&
        Number.isSafeInteger(value.authGeneration) &&
        Number(value.authGeneration) > 0 &&
        Number(value.authGeneration) <= 2147483647 &&
        typeof value.expiresAt === 'string' &&
        /^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/.test(value.expiresAt) &&
        Number.isFinite(Date.parse(value.expiresAt)) &&
        new Date(value.expiresAt).toISOString() === value.expiresAt
    );
}
export async function readContext(
    signal: AbortSignal,
): Promise<SessionContextData> {
    const response = await withRecoverySlot(
        () =>
            http.getClient().request({
                method: 'get',
                url: context.url(),
                headers: { Accept: 'application/json' },
                signal,
            }),
        signal,
    );
    if (response.status === 401 || response.status === 419) {
        return {
            user: null,
            broadcastToken: null,
            authGeneration: null,
            expiresAt: null,
            locale: response.headers['content-language'] === 'en' ? 'en' : 'fr',
        };
    }
    if (response.status !== 200)
        throw new Error('Access context is unavailable');
    const data: unknown = JSON.parse(response.data);
    if (!isSessionContext(data))
        throw new Error('Invalid access context contract');
    return data;
}

export function isContextRequest(url: string): boolean {
    return url === context.url();
}
