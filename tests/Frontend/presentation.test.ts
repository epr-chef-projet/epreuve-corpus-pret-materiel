import { afterEach, describe, expect, it, vi } from 'vitest';
import { createPresentation } from '@/shared/i18n/presentation';
import { commonMessages } from '@/shared/i18n/messages';
import { identityMessages } from '@/modules/identity-access/public/access';
import { fieldError } from '@/shared/i18n/fieldError';
import { explainIssue } from '@/shared/i18n/issue';

afterEach(() => localStorage.clear());
describe('presentation contracts', () => {
    it('remains usable and signals when storing the language is refused', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('Storage unavailable');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('Storage unavailable');
        });
        const presentation = createPresentation(commonMessages);
        expect(presentation.storageUnavailable.value).toBe(true);
        presentation.setLocale('en');
        expect(presentation.i18n.global.locale.value).toBe('en');
        expect(presentation.storageUnavailable.value).toBe(true);
        presentation.dispose();
    });
    it('reformats a known native error without resubmitting and keeps unknown detail labelled', () => {
        const presentation = createPresentation({
            fr: { ...commonMessages.fr, ...identityMessages.fr },
            en: { ...commonMessages.en, ...identityMessages.en },
        });
        const detail = {
            locale: 'fr',
            fieldErrors: {
                login: [{ messageKey: 'identity.credentials', parameters: {} }],
            },
        };
        expect(
            fieldError(detail, 'login', 'native', presentation.i18n.global),
        ).toContain('incorrect');
        presentation.setLocale('en');
        expect(document.documentElement.lang).toBe('en');
        expect(
            fieldError(detail, 'login', 'native', presentation.i18n.global),
        ).toBe('The account ID or password is incorrect.');
        const unknown = fieldError(
            {
                locale: 'fr',
                fieldErrors: {
                    login: [{ messageKey: 'missing.secret', parameters: {} }],
                },
            },
            'login',
            'Détail natif',
            presentation.i18n.global,
        );
        expect(unknown).toContain('Detail received in: fr');
        expect(unknown).toContain('Détail natif');
        expect(unknown).not.toContain('missing.secret');
        presentation.dispose();
    });
    it('does not infer a success or expose a raw unknown issue code', () => {
        const presentation = createPresentation(commonMessages);
        const result = explainIssue(
            {
                errorCode: 'SQL secret',
                messageKey: 'SQL secret',
                parameters: { internal: 'private' },
                outcome: 'unknown',
            },
            presentation.i18n.global,
        );
        expect(result.outcome).toBe('unknown');
        expect(result.message).not.toContain('SQL');
        expect(result.next).toBe('');
        presentation.dispose();
    });
    it('rejects persisted unsupported languages and restores only the language preference', () => {
        localStorage.setItem('epr.presentationLocale', 'untrusted');
        const first = createPresentation(commonMessages);
        expect(first.i18n.global.locale.value).toBe('fr');
        first.setLocale('en');
        first.dispose();
        const next = createPresentation(commonMessages);
        expect(next.i18n.global.locale.value).toBe('en');
        next.dispose();
        expect(Object.keys(localStorage)).toEqual(['epr.presentationLocale']);
    });
});
