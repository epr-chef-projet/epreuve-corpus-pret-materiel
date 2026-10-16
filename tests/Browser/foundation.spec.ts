import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = JSON.parse(
    readFileSync('.epr-local/identity-testing.json', 'utf8'),
) as {
    password: string;
    users: { login: string }[];
};
const actorLogin = fixture.users[2].login;

test('built page and real login use local assets without runtime errors', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/');
    await page.getByRole('link', { name: 'Log in', exact: true }).click();
    await expect(page.getByLabel('Identifiant')).toBeVisible();
    await page.getByLabel('Identifiant').fill(actorLogin);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fixture.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await expect(page).toHaveURL(/\/dashboard$/);
    expect(await page.evaluate(() => isSecureContext && !!crypto.subtle)).toBe(
        true,
    );
    expect(errors).toEqual([]);
    const cookie = (await page.context().cookies()).find(
        (entry) => entry.name === 'epr_browser_session',
    );
    expect(cookie?.httpOnly).toBe(true);
    expect(cookie?.sameSite).toBe('Lax');
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await expect(page).toHaveURL(/\/$/);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
});

test('private realtime authorizes the actor, receives a server event and leaves', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Identifiant').fill(actorLogin);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fixture.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
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
    await page.getByLabel('Identifiant').fill(actorLogin);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fixture.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
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
