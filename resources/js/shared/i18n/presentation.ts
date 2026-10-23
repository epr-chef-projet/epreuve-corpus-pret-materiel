import { ref, watch, type InjectionKey } from 'vue';
import { createI18n } from 'vue-i18n';

export type PresentationLocale = 'fr' | 'en';
type Messages = { [key: string]: string | Messages };

export function createPresentation(
    messages: Record<PresentationLocale, Messages>,
) {
    const storageUnavailable = ref(false);
    let selected: PresentationLocale = 'fr';
    try {
        const stored = localStorage.getItem('epr.presentationLocale');
        if (stored === 'fr' || stored === 'en') selected = stored;
    } catch {
        storageUnavailable.value = true;
    }
    const i18n = createI18n({
        legacy: false,
        locale: selected,
        fallbackLocale: 'fr',
        messages,
        missingWarn: false,
        fallbackWarn: false,
    });
    const stop = watch(
        i18n.global.locale,
        (locale) => {
            document.documentElement.lang = locale;
        },
        { immediate: true, flush: 'sync' },
    );
    function setLocale(locale: PresentationLocale) {
        i18n.global.locale.value = locale;
        try {
            localStorage.setItem('epr.presentationLocale', locale);
            storageUnavailable.value = false;
        } catch {
            storageUnavailable.value = true;
        }
    }
    return { i18n, storageUnavailable, setLocale, dispose: stop };
}

export type Presentation = ReturnType<typeof createPresentation>;
export const presentationKey: InjectionKey<Presentation> =
    Symbol('presentation');
