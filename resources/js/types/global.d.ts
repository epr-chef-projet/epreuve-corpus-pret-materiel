import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { ReverbPublicConfig } from '@/shared/realtime';
import '@inertiajs/vue3';

declare global {
    interface ViteTypeOptions {
        strictImportMetaEnv: unknown;
    }

    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            reverb: ReverbPublicConfig | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }
}
