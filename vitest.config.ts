import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        include: ['tests/Frontend/**/*.test.ts'],
        environment: 'jsdom',
        passWithNoTests: false,
        allowOnly: false,
        retry: 0,
        maxWorkers: 1,
        testTimeout: 10_000,
        hookTimeout: 10_000,
        clearMocks: true,
        restoreMocks: true,
    },
});
