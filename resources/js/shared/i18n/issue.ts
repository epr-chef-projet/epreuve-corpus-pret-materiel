import type { MessagePresenter } from './presenter';
import type { IssueData } from '@/types/generated/types/App/Shared/Errors';

function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
const actions = [
    'correctInput',
    'reload',
    'signIn',
    'verifyOperation',
    'retrySameOperation',
    'contactStaff',
    'contactManager',
];
export function explainIssue(value: unknown, composer: MessagePresenter) {
    const fallback = composer.t('issues.operations.unexpected_failure');
    if (!record(value))
        return {
            message: fallback,
            next: '',
            outcome: 'unknown',
            correlationId: '',
            signIn: false,
        };
    const outcome: IssueData['outcome'] =
        value.outcome === 'notApplied' || value.outcome === 'applied'
            ? value.outcome
            : 'unknown';
    const valid =
        typeof value.errorCode === 'string' &&
        value.messageKey === 'issues.' + value.errorCode &&
        composer.te(value.messageKey) &&
        record(value.parameters) &&
        Object.keys(value.parameters).length === 0;
    const next = value.nextAction;
    const nextKey =
        record(next) &&
        typeof next.kind === 'string' &&
        actions.includes(next.kind) &&
        next.messageKey === 'actions.' + next.kind
            ? next.messageKey
            : null;
    const correlationId =
        typeof value.correlationId === 'string' &&
        /^[0-9a-f-]{36}$/.test(value.correlationId)
            ? value.correlationId
            : '';
    return {
        message: valid ? composer.t(String(value.messageKey)) : fallback,
        next: nextKey && composer.te(nextKey) ? composer.t(nextKey) : '',
        outcome,
        correlationId,
        signIn: nextKey === 'actions.signIn',
    };
}
