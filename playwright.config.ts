import { defineConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { browserEnvironment } from './tests/Browser/environment';

for (const path of ['framework/sessions', 'framework/views', 'logs']) {
    mkdirSync(`${browserEnvironment.LARAVEL_STORAGE_PATH}/${path}`, {
        recursive: true,
        mode: 0o700,
    });
}

export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    retries: 0,
    forbidOnly: true,
    timeout: 30_000,
    fullyParallel: false,
    use: {
        baseURL: browserEnvironment.APP_URL,
        browserName: 'chromium',
        headless: true,
        trace: 'off',
        screenshot: 'off',
        testIdAttribute: 'data-test',
    },
    webServer: [
        {
            command:
                'php artisan serve --host=127.0.0.1 --port=8000 --no-reload',
            url: `${browserEnvironment.APP_URL}/login`,
            env: browserEnvironment,
            reuseExistingServer: false,
            gracefulShutdown: { signal: 'SIGTERM', timeout: 10_000 },
        },
        {
            command: 'php artisan reverb:start --host=127.0.0.1 --port=8081',
            port: 8081,
            env: browserEnvironment,
            reuseExistingServer: false,
            gracefulShutdown: { signal: 'SIGTERM', timeout: 10_000 },
        },
    ],
    reporter: [['list']],
});
