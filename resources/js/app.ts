import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { focus } from '@/shared/directives/focus';
import { createEcho, echoKey } from '@/shared/realtime';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app, { page }) => {
        app.use(createPinia());
        const echo = createEcho(page.props.reverb);
        app.provide(echoKey, echo);
        const disconnect = () => echo?.disconnect();
        window.addEventListener('pagehide', disconnect, { once: true });
        import.meta.hot?.dispose(() => {
            window.removeEventListener('pagehide', disconnect);
            disconnect();
        });
        app.directive('focus', focus);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
const disposeFlashToast = initializeFlashToast();
import.meta.hot?.dispose(disposeFlashToast);
