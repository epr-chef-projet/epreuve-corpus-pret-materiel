import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const environment = loadEnv(mode, process.cwd(), '');
    const browserTests = mode === 'browser';
    const appUrl = new URL(environment.APP_URL);
    const port = Number(environment.VITE_PORT || 5173);
    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                hotFile: browserTests
                    ? 'storage/framework/browser/hot'
                    : 'public/hot',
                buildDirectory: browserTests ? 'browser-build' : 'build',
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                        optimizedFallbacks: false,
                    }),
                ],
            }),
            inertia(),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
            browserTests && {
                name: 'browser-test-page',
                enforce: 'pre',
                transform(source, id) {
                    if (!id.endsWith('/resources/js/app.ts')) return;
                    return source.replace(
                        'void createInertiaApp({',
                        `void createInertiaApp({
                            resolve: async (name) => {
                                if (name === '_foundation/Probe') {
                                    return import('/tests/Fixtures/pages/Probe.vue');
                                }
                                if (name === '_foundation/SessionProbe') {
                                    return import('/tests/Fixtures/pages/SessionProbe.vue');
                                }
                                const pages = import.meta.glob('/resources/js/pages/**/*.vue');
                                const page = pages['/resources/js/pages/' + name + '.vue'];
                                if (!page) throw new Error('Page not found: ' + name);
                                return page();
                            },`,
                    );
                },
            },
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            origin: `${appUrl.protocol}//${appUrl.hostname}:${port}`,
            cors: { origin: appUrl.origin },
            hmr: { host: appUrl.hostname, clientPort: port },
            watch: {
                ignored: [
                    '**/.agents/**',
                    '**/.claude/**',
                    '**/.cursor/**',
                    '**/.junie/**',
                    '**/vendor/**',
                ],
            },
        },
    };
});
