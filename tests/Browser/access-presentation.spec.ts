import { expect, test, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';

declare global {
    interface Window {
        sessionProbeEvents?: number;
        sessionProbeHostileSubscribed?: boolean;
    }
}

const accounts = JSON.parse(
    readFileSync('.epr-local/identity-testing.json', 'utf8'),
) as {
    password: string;
    users: { login: string; displayName: string; role: string }[];
};
const borrower = accounts.users.findIndex((user) => user.role === 'borrower');
const pageErrors: string[] = [];
test.beforeEach(({ page, context }) => {
    pageErrors.length = 0;
    page.on('pageerror', (error) => pageErrors.push(error.message));
    context.on('page', (opened) =>
        opened.on('pageerror', (error) => pageErrors.push(error.message)),
    );
});
test.afterEach(() => {
    expect(pageErrors).toEqual([]);
});
async function signIn(page: Page, index = borrower) {
    await page.goto('/login');
    await page
        .getByLabel('Identifiant', { exact: true })
        .fill(accounts.users[index].login);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(accounts.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await expect(page).toHaveURL(
        accounts.users[index].role === 'borrower'
            ? /\/catalogue$/
            : /\/demandes$/,
    );
    await expect(
        page
            .getByText(accounts.users[index].displayName, { exact: true })
            .first(),
    ).toBeVisible();
}
async function xsrf(page: Page) {
    const cookie = (await page.context().cookies()).find(
        (entry) => entry.name === 'XSRF-TOKEN',
    );
    if (!cookie) throw new Error('Missing fixture CSRF cookie');
    return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) };
}
async function logout(page: Page) {
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await expect(page).toHaveURL(/\/$/);
}

test('language changes preserve input and reformat validation without another submission', async ({
    page,
}) => {
    const posts: string[] = [];
    page.on('request', (request) => {
        if (request.method() === 'POST') posts.push(request.url());
    });
    await page.goto('/login');
    await page
        .getByLabel('Identifiant', { exact: true })
        .fill('unknown-synthetic');
    await page.getByLabel('Mot de passe', { exact: true }).fill('wrong');
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await expect(page.getByRole('alert').first()).toContainText('incorrect');
    await page.getByLabel('Langue de l’interface').selectOption('en');
    await expect(page.getByRole('alert').first()).toHaveText(
        'The account ID or password is incorrect.',
    );
    await expect(page.getByLabel('Account ID')).toHaveValue(
        'unknown-synthetic',
    );
    expect(posts).toHaveLength(1);
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(
        page.getByRole('heading', { name: 'Sign in to your workshop' }),
    ).toBeVisible();
});

test('a second tab closes its private screen on logout and renews for another account', async ({
    page,
    context,
}) => {
    await signIn(page);
    const other = await context.newPage();
    await other.goto('/catalogue');
    await expect(
        other
            .getByText(accounts.users[borrower].displayName, { exact: true })
            .first(),
    ).toBeVisible();
    await logout(page);
    await expect(
        other.getByText(accounts.users[borrower].displayName, { exact: true }),
    ).toHaveCount(0);
    await signIn(page, 6);
    await expect(
        other.getByText(accounts.users[6].displayName, { exact: true }).first(),
    ).toBeVisible();
    await expect(
        other.getByText(accounts.users[borrower].displayName, { exact: true }),
    ).toHaveCount(0);
    await logout(page);
    await signIn(page, borrower);
    await expect(
        other
            .getByText(accounts.users[borrower].displayName, { exact: true })
            .first(),
    ).toBeVisible();
});

test('a late private response cannot restore the previous account after another tab changes it', async ({
    page,
    context,
}) => {
    await signIn(page);
    const other = await context.newPage();
    await other.goto('/catalogue');
    let release!: () => void;
    const barrier = new Promise<void>((resolve) => {
        release = resolve;
    });
    let captured!: () => void;
    const arrived = new Promise<void>((resolve) => {
        captured = resolve;
    });
    await other.route('**/mes-demandes', async (route) => {
        const response = await route.fetch();
        captured();
        await barrier;
        await route.fulfill({ response });
    });
    await other
        .getByRole('link', { name: 'Mes demandes', exact: true })
        .click({ noWaitAfter: true });
    await Promise.race([
        arrived,
        new Promise((_, reject) =>
            setTimeout(
                () =>
                    reject(new Error('Private response did not reach barrier')),
                5000,
            ),
        ),
    ]);
    await logout(page);
    await signIn(page, 6);
    release();
    await expect(
        other.getByText(accounts.users[6].displayName, { exact: true }).first(),
    ).toBeVisible();
    await expect(
        other.getByText(accounts.users[borrower].displayName, { exact: true }),
    ).toHaveCount(0);
});

test('the real notifications queue reaches two independently owned consumers', async ({
    page,
}) => {
    await signIn(page);
    await page.goto('/__foundation/session');
    await expect(page.getByTestId('session-subscribed')).toHaveText('true');
    await page
        .getByRole('button', { name: 'Add consumer', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Publish session', exact: true })
        .click();
    await expect(page.getByTestId('session-received')).toHaveText('1');
    await expect(page.getByTestId('later-received')).toHaveText('1');
    await page
        .getByRole('button', { name: 'Release first', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Publish session', exact: true })
        .click();
    await expect(page.getByTestId('later-received')).toHaveText('2');
    await expect(page.getByTestId('session-received')).toHaveText('1');
});

test('a queued publication released after logout cannot feed a deliberately retained old socket', async ({
    page,
}) => {
    await signIn(page);
    await page.goto('/__foundation/session');
    const oldContext = (await (
        await page.request.get('/access/context')
    ).json()) as { broadcastToken: string };
    const oldRouteHash = createHash('sha256')
        .update(`epr.session.${oldContext.broadcastToken}`)
        .digest('hex');
    await page
        .getByRole('button', { name: 'Keep hostile socket', exact: true })
        .click();
    await expect
        .poll(() => page.evaluate(() => window.sessionProbeHostileSubscribed))
        .toBe(true);
    const dispatched = await page.request.post(
        '/__foundation/session-publish',
        { headers: await xsrf(page), data: { barrier: true } },
    );
    expect(dispatched.status()).toBe(202);
    const { eventId } = (await dispatched.json()) as { eventId: string };
    await expect
        .poll(
            async () =>
                (
                    (await (
                        await page.request.get(
                            `/__foundation/session-probe/${eventId}`,
                        )
                    ).json()) as { started: boolean }
                ).started,
        )
        .toBe(true);
    await logout(page);
    const released = await page.request.post(
        `/__foundation/session-probe/${eventId}/release`,
        { headers: await xsrf(page) },
    );
    expect(released.status()).toBe(204);
    await expect
        .poll(
            async () =>
                (
                    (await (
                        await page.request.get(
                            `/__foundation/session-probe/${eventId}`,
                        )
                    ).json()) as { result: unknown }
                ).result,
        )
        .not.toBeNull();
    const result = (await (
        await page.request.get(`/__foundation/session-probe/${eventId}`)
    ).json()) as { result: { routes: string[] } };
    expect(result.result.routes).not.toContain(oldRouteHash);
    expect(await page.evaluate(() => window.sessionProbeEvents)).toBe(0);
});

test('session channel admission checks CSRF and refuses a foreign route', async ({
    page,
}) => {
    await signIn(page);
    const context = (await (
        await page.request.get('/access/context')
    ).json()) as { broadcastToken: string };
    const body = {
        socket_id: '123.456',
        channel_name: `private-epr.session.${context.broadcastToken}`,
    };
    expect(
        (
            await page.request.post('/broadcasting/auth', { data: body })
        ).status(),
    ).toBe(419);
    expect(
        (
            await page.request.post('/broadcasting/auth', {
                headers: await xsrf(page),
                data: body,
            })
        ).status(),
    ).toBe(200);
    expect(
        (
            await page.request.post('/broadcasting/auth', {
                headers: await xsrf(page),
                data: {
                    ...body,
                    channel_name: `private-epr.session.${'f'.repeat(64)}`,
                },
            })
        ).status(),
    ).toBe(403);
});

test('HTTP loss and resume keep the limits visible without replaying a mutation', async ({
    page,
    context,
}) => {
    await signIn(page);
    let posts = 0;
    page.on('request', (request) => {
        if (
            request.method() === 'POST' &&
            !request.url().includes('/broadcasting/auth')
        )
            posts++;
    });
    await context.setOffline(true);
    await expect(
        page.getByRole('status').filter({ hasText: 'Connexion interrompue' }),
    ).toBeVisible();
    await context.setOffline(false);
    await expect(
        page
            .getByText(accounts.users[borrower].displayName, { exact: true })
            .first(),
    ).toBeVisible();
    await expect(
        page.getByRole('status').filter({ hasText: 'Connexion interrompue' }),
    ).toHaveCount(0);
    expect(posts).toBe(0);
});

test('phone, keyboard and enlarged English layout keep essential controls available', async ({
    page,
}) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await signIn(page);
    await page.getByLabel('Langue de l’interface').selectOption('en');
    await expect(
        page.getByRole('heading', { name: 'Catalogue', exact: true }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page
        .getByRole('button', { name: 'Toggle navigation', exact: true })
        .first()
        .click();
    await expect(
        page.getByRole('link', { name: 'My requests', exact: true }),
    ).toBeVisible();
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.evaluate(() => {
        document.documentElement.style.zoom = '2';
    });
    await expect(page.getByLabel('Interface language')).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
});

test('the private transport reconnects and receives a new queued event after a real socket loss', async ({
    page,
}) => {
    await signIn(page);
    await page.goto('/__foundation/session');
    await expect(page.getByTestId('session-subscribed')).toHaveText('true');
    await page
        .getByRole('button', { name: 'Disconnect transport', exact: true })
        .click();
    await expect(page.getByTestId('session-subscribed')).toHaveText('false');
    await expect(page.getByTestId('session-subscribed')).toHaveText('true');
    await page
        .getByRole('button', { name: 'Publish session', exact: true })
        .click();
    await expect(page.getByTestId('session-received')).toHaveText('1');
});
