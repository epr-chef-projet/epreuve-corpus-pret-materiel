import type { MessagePresenter } from './presenter';

function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
export function fieldError(
    presentation: unknown,
    field: string,
    native: string | undefined,
    composer: MessagePresenter,
) {
    if (!native) return '';
    if (record(presentation) && record(presentation.fieldErrors)) {
        const errors = presentation.fieldErrors[field];
        const first: unknown = Array.isArray(errors) ? errors[0] : null;
        if (
            record(first) &&
            typeof first.messageKey === 'string' &&
            composer.te(first.messageKey) &&
            record(first.parameters)
        ) {
            const parameters = first.parameters;
            const valid =
                Object.keys(parameters).length === 0 ||
                (first.messageKey === 'validation.max' &&
                    Object.keys(parameters).length === 1 &&
                    Number.isInteger(parameters.max));
            if (valid) return composer.t(first.messageKey, parameters);
        }
    }
    const locale =
        record(presentation) &&
        (presentation.locale === 'fr' || presentation.locale === 'en')
            ? presentation.locale
            : 'fr';
    return (
        composer.t('common.validationFallback') +
        ' ' +
        composer.t('common.nativeDetail', { locale }) +
        ' ' +
        native
    );
}
