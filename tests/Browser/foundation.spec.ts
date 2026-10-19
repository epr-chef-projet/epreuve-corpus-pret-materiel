import { expect, test, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';

declare global {
    interface Window {
        reportPreviousIdentity?: () => Promise<void>;
    }
}

const fixture = JSON.parse(
    readFileSync('.epr-local/identity-testing.json', 'utf8'),
) as {
    password: string;
    users: { login: string; displayName: string }[];
};
const actor = fixture.users[2];
const actorLogin = actor.login;
const otherActor = fixture.users[6];

async function signIn(page: Page, login: string) {
    await page.getByLabel('Identifiant').fill(login);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fixture.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
}

async function watchPreviousIdentity(page: Page) {
    const leaks: string[] = [];
    await page.exposeFunction('reportPreviousIdentity', () => {
        leaks.push('Previous account rendered after leaving its session');
    });
    const observe = (displayName: string) => {
        const check = () => {
            if (document.body?.innerText.includes(displayName)) {
                const report = window.reportPreviousIdentity;
                if (!report) {
                    throw new Error('Missing browser privacy observer');
                }
                void report();
            }
        };
        new MutationObserver(check).observe(document, {
            subtree: true,
            childList: true,
            characterData: true,
        });
        check();
    };
    await page.addInitScript(observe, actor.displayName);
    await page.evaluate(observe, actor.displayName);

    return leaks;
}

async function verifyPrivateHistory(page: Page) {
    const leaks = await watchPreviousIdentity(page);
    await page.goBack();
    await expect(page.getByLabel('Identifiant')).toBeVisible();
    await expect(
        page.getByText(actor.displayName, { exact: true }),
    ).toHaveCount(0);
    await page.goForward();
    await expect(
        page.getByRole('heading', { name: 'Accès à l’atelier' }),
    ).toHaveCount(0);
    await page.goto('/login');
    await signIn(page, otherActor.login);
    await expect(
        page.getByText(otherActor.displayName, { exact: true }).first(),
    ).toBeVisible();
    for (const direction of ['back', 'forward'] as const) {
        for (let step = 0; step < 3; step++) {
            if (direction === 'back') {
                await page.goBack();
            } else {
                await page.goForward();
            }
            await expect(
                page.getByText(actor.displayName, { exact: true }),
            ).toHaveCount(0);
        }
    }
    await page.goto('/dashboard');
    await expect(
        page.getByText(otherActor.displayName, { exact: true }).first(),
    ).toBeVisible();
    expect(leaks).toEqual([]);
}

test('built page and real login protect private history after logout', async ({
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
    await page.goto('/dashboard?history=previous');
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await expect(page).toHaveURL(/\/$/);
    await verifyPrivateHistory(page);
    await page.goto('/dashboard');
    await expect(
        page.getByText(otherActor.displayName, { exact: true }).first(),
    ).toBeVisible();
    expect(errors).toEqual([]);
});

for (const reason of ['expired', 'missing'] as const) {
    test(`private history stays inaccessible after session becomes ${reason}`, async ({
        page,
    }) => {
        await page.goto('/login');
        await signIn(page, actorLogin);
        await page.goto('/dashboard?history=previous');
        await page.goto('/dashboard?history=current');
        const xsrf = (await page.context().cookies()).find(
            (cookie) => cookie.name === 'XSRF-TOKEN',
        );
        if (!xsrf) {
            throw new Error('Missing CSRF cookie in browser test');
        }
        const response = await page.request.post('/__foundation/end-session', {
            headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) },
            data: { reason },
        });
        expect(response.status()).toBe(204);
        await page.reload();
        await expect(page).toHaveURL(/\/login$/);
        await verifyPrivateHistory(page);
    });
}

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
