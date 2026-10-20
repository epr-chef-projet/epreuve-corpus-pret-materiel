import { createInertiaApp } from '@inertiajs/vue3';
import { commonMessages } from '@/shared/i18n/messages';
import {
    createPresentation,
    presentationKey,
} from '@/shared/i18n/presentation';
import {
    accessLifecycleKey,
    identityMessages,
    installAccessLifecycle,
    installSessionTransport,
    sessionSubscriptionsKey,
    useAccessStore,
} from '@/modules/identity-access/public/access';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import { createPinia } from 'pinia';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { focus } from '@/shared/directives/focus';
import { createEcho, echoKey } from '@/shared/realtime';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const presentation = createPresentation({
    fr: {
        ...commonMessages.fr,
        ...identityMessages.fr,
        issues: { ...commonMessages.fr.issues, ...identityMessages.fr.issues },
    },
    en: {
        ...commonMessages.en,
        ...identityMessages.en,
        issues: { ...commonMessages.en.issues, ...identityMessages.en.issues },
    },
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome' || name === 'Error':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            default:
                return AppLayout;
        }
    },
    withApp: (app, { page }) => {
        app.use(createPinia());
        app.use(presentation.i18n);
        app.provide(presentationKey, presentation);
        const lifecycle = installAccessLifecycle(presentation);
        app.provide(accessLifecycleKey, lifecycle);
        const echo = createEcho(page.props.reverb ?? null);
        app.provide(echoKey, echo);
        const access = useAccessStore();
        const transport = echo ? installSessionTransport(echo, access) : null;
        app.provide(sessionSubscriptionsKey, transport?.subscriptions ?? null);
        const cleanToasts = watch(
            () => access.generation,
            () => toast.dismiss(),
            { flush: 'sync' },
        );
        const disposeFlashToast = initializeFlashToast(
            () => access.status === 'active' && !access.confirming,
        );
        const disconnect = () => echo?.disconnect();
        window.addEventListener('pagehide', disconnect, { once: true });
        import.meta.hot?.dispose(() => {
            window.removeEventListener('pagehide', disconnect);
            disconnect();
            transport?.dispose();
            cleanToasts();
            disposeFlashToast();
            lifecycle.dispose();
            presentation.dispose();
        });
        app.directive('focus', focus);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
const disposeTheme = initializeTheme();
import.meta.hot?.dispose(disposeTheme);
