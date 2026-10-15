import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { browserEnvironment } from './environment';

let actorEmail: string;

test.beforeEach(() => {
    actorEmail = `browser-${randomUUID()}@example.invalid`;
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\User::factory()->create(["name" => "Browser Probe", "email" => "${actorEmail}", "password" => "Foundation-Probe-47!"]);`,
        ],
        { env: { ...process.env, ...browserEnvironment } },
    );
});

test.afterEach(() => {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\User::where("email", "${actorEmail}")->delete();`,
        ],
        { env: { ...process.env, ...browserEnvironment } },
    );
});

test('built page and real login use local assets without runtime errors', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/');
    await page.getByRole('link', { name: 'Log in', exact: true }).click();
    await expect(page.getByLabel('Email address')).toBeVisible();
    await page.getByLabel('Email address').fill(actorEmail);
    await page
        .getByLabel('Password', { exact: true })
        .fill('Foundation-Probe-47!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    expect(await page.evaluate(() => isSecureContext && !!crypto.subtle)).toBe(
        true,
    );
    expect(errors).toEqual([]);
});

test('private realtime authorizes the actor, receives a server event and leaves', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(actorEmail);
    await page
        .getByLabel('Password', { exact: true })
        .fill('Foundation-Probe-47!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/__foundation');
    await expect(page.getByTestId('subscribed')).toHaveText('true');
    await page.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(page.getByTestId('received')).toHaveText('true');
    await page.getByRole('button', { name: 'Leave', exact: true }).click();
    await expect(page.getByTestId('subscribed')).toHaveText('false');
});

test('QR image does not execute SVG scripts or fetch external resources', async ({
    page,
}) => {
    const external: string[] = [];
    page.on('request', (request) => {
        if (request.url().includes('qr-fixture.invalid'))
            external.push(request.url());
    });
    await page.goto('/login');
    await page.getByLabel('Email address').fill(actorEmail);
    await page
        .getByLabel('Password', { exact: true })
        .fill('Foundation-Probe-47!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/__foundation');
    const qr = page.getByRole('img', {
        name: 'Two-factor authentication QR code',
    });
    await expect(qr).toBeVisible();
    await expect
        .poll(async () =>
            qr.evaluate(
                (element: HTMLImageElement) =>
                    element.complete && element.naturalWidth > 0,
            ),
        )
        .toBe(true);
    expect(await page.evaluate(() => 'qrExecuted' in window)).toBe(false);
    expect(external).toEqual([]);
});
