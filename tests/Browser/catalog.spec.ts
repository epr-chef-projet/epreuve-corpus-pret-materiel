import { expect, test, type Page } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import {
    observeCatalogRendering,
    takeCatalogRendering,
} from './catalogRendering';

const accounts = JSON.parse(
    readFileSync('.epr-local/identity-testing.json', 'utf8'),
) as {
    password: string;
    users: { id: string; login: string; role: string; workshopId: string }[];
};
const manager = accounts.users.find(
    (user) => user.login === 'atelier-1-manager',
)!;
const keys = new Map<string, string>();
const runId = randomUUID();
const errors: string[] = [];
function observe(page: Page) {
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('request', (request) => {
        if (
            !['POST', 'PATCH'].includes(request.method()) ||
            !new URL(request.url()).pathname.startsWith('/gestion/materiels')
        )
            return;
        const body: unknown = request.postDataJSON();
        if (
            body &&
            typeof body === 'object' &&
            'operationKey' in body &&
            typeof body.operationKey === 'string' &&
            /^[0-9a-f-]{36}$/.test(body.operationKey)
        )
            keys.set(body.operationKey, manager.id);
    });
}
async function signIn(page: Page, login = manager.login) {
    observe(page);
    await page.goto('/login');
    await page.getByLabel('Identifiant', { exact: true }).fill(login);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(accounts.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await expect(page).toHaveURL(/\/demandes$/);
    if ((page.viewportSize()?.width ?? 1280) < 768)
        await page
            .getByRole('button', {
                name: 'Ouvrir ou fermer la navigation',
                exact: true,
            })
            .first()
            .click();
    await page
        .getByRole('link', { name: 'Gestion des matériels', exact: true })
        .click();
    if ((page.viewportSize()?.width ?? 1280) < 768)
        await page.keyboard.press('Escape');
    await expect(
        page.getByRole('heading', {
            name: 'Gestion des matériels',
            exact: true,
        }),
    ).toBeVisible();
}
async function create(page: Page, name: string) {
    await page
        .getByRole('link', { name: 'Créer un matériel', exact: true })
        .click();
    await page.getByLabel('Nom *', { exact: true }).fill(name);
    await page
        .getByLabel('Catégorie *', { exact: true })
        .fill('A03 browser tools');
    await page
        .getByLabel('Description (facultative)', { exact: true })
        .fill('Synthetic description <script>window.catalogXss=true</script>');
    await page.getByLabel('Quantité initiale *', { exact: true }).fill('2');
    await page
        .getByRole('button', { name: 'Créer le matériel', exact: true })
        .click();
    await expect(page).toHaveURL(/\/gestion\/materiels\/[0-9a-f-]{36}$/);
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    return page.url();
}
test.beforeEach(() => {
    errors.length = 0;
});
test.afterEach(() => {
    expect(errors).toEqual([]);
});
test.afterAll(() => {
    if (!keys.size) return;
    const pairs = [...keys]
        .map(([key, actor]) => `('${actor}'::uuid,'${key}'::uuid)`)
        .join(',');
    writeFileSync(
        `.epr-local/catalog-browser-cleanup-${runId}.sql`,
        `BEGIN;\nDO $guard$ BEGIN IF current_database()<>'testing' OR NOT EXISTS(SELECT FROM pg_roles WHERE rolname=session_user AND rolsuper) THEN RAISE EXCEPTION 'Testing cleanup target refused'; END IF; END $guard$;\nCREATE TEMP TABLE catalog_fixture_cleanup ON COMMIT DROP AS SELECT equipment_id FROM epr.operation_receipts WHERE (actor_id,operation_key) IN (${pairs}) AND operation_type='catalog.equipment.create';\nDELETE FROM epr.operation_receipts WHERE (actor_id,operation_key) IN (${pairs}) AND operation_type IN ('catalog.equipment.create','catalog.equipment.update_text');\nDELETE FROM epr.catalog_equipment WHERE id IN(SELECT equipment_id FROM catalog_fixture_cleanup);\nCOMMIT;\n`,
        { mode: 0o600 },
    );
});

for (const width of [1280, 360]) {
    test(`catalog rendering remains stable during search and its automatic recheck at ${width}px`, async ({
        page,
    }) => {
        test.setTimeout(45_000);
        await page.setViewportSize({ width, height: 900 });
        await signIn(page);
        const name = 'A03 stable ' + randomUUID();
        const url = await create(page, name);
        await page
            .getByRole('link', { name: 'Retour à la liste', exact: true })
            .click();
        await expect(page.locator('ul[aria-busy]')).toHaveAttribute(
            'aria-busy',
            'false',
        );
        const path = new URL(url).pathname;
        const link = page.getByRole('link', { name, exact: true });
        await page.getByLabel('Fragment du nom', { exact: true }).fill(name);
        const initialRead = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname ===
                '/gestion/materiels/lectures',
        );
        await page
            .getByRole('button', { name: 'Rechercher', exact: true })
            .click();
        await initialRead;
        await expect(page.locator('ul[aria-busy]')).toHaveAttribute(
            'aria-busy',
            'false',
        );
        await expect(link).toBeVisible();
        if (width === 360)
            await page.getByLabel('Langue de l’interface').selectOption('en');
        await observeCatalogRendering(page, `a[href="${path}"]`);
        const search = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === '/gestion/materiels' &&
                response.request().method() === 'GET',
        );
        const checked = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname ===
                '/gestion/materiels/lectures',
        );
        await page
            .getByRole('button', {
                name: width === 360 ? 'Search' : 'Rechercher',
                exact: true,
            })
            .click();
        await Promise.all([search, checked]);
        await expect(page.locator('ul[aria-busy]')).toHaveAttribute(
            'aria-busy',
            'false',
        );
        // Observe the whole periodic window, including any intervening notification.
        await page.waitForTimeout(16_000);
        await expect(page.locator('ul[aria-busy]')).toHaveAttribute(
            'aria-busy',
            'false',
        );
        const renders = await takeCatalogRendering(page);
        expect(renders.length).toBeGreaterThan(0);
        expect(
            new Set(renders.map((render) => render.top)).size,
            JSON.stringify(renders),
        ).toBe(1);
        expect(
            renders.some((render) => render.top === null || render.pending),
        ).toBe(false);
    });
}

test('catalog rendering does not present a normal save as an unknown result or move the editing fields', async ({
    page,
}) => {
    await signIn(page);
    await page
        .getByRole('link', { name: 'Créer un matériel', exact: true })
        .click();
    const name = 'A03 save feedback ' + randomUUID();
    await page.getByLabel('Nom *', { exact: true }).fill(name);
    await page
        .getByLabel('Catégorie *', { exact: true })
        .fill('A03 browser tools');
    await page.getByLabel('Quantité initiale *', { exact: true }).fill('2');
    await observeCatalogRendering(page, '#equipment-name');
    await page
        .getByRole('button', { name: 'Créer le matériel', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    expect(
        (await takeCatalogRendering(page)).some(
            (render) => render.pending || render.conflict,
        ),
    ).toBe(false);
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Enregistrement confirmé.' }),
    ).toBeVisible();
    for (const version of [1, 2]) {
        await page
            .getByLabel('Nom *', { exact: true })
            .fill(name + ' ' + version);
        await observeCatalogRendering(page, '#equipment-name');
        await page
            .getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            })
            .click();
        await expect(
            page.getByRole('heading', {
                name: name + ' ' + version,
                exact: true,
            }),
        ).toBeVisible();
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'Enregistrement confirmé.' }),
        ).toBeVisible();
        const renders = await takeCatalogRendering(page);
        expect(
            renders.some((render) => render.pending || render.conflict),
        ).toBe(false);
        expect(new Set(renders.map((render) => render.top)).size).toBe(1);
    }
});

test('a slow save stays visible without a false uncertainty and releases controls on confirmation', async ({
    page,
}) => {
    await signIn(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const name = 'S' + randomUUID().slice(0, 6);
    const nextName = 'T' + name.slice(1);
    const url = await create(page, name);
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Enregistrement confirmé.' }),
    ).toBeVisible();
    await page.evaluate(() => {
        document.documentElement.style.zoom = '2';
    });
    await page.getByLabel('Nom *', { exact: true }).fill(nextName);
    let release = () => {};
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    let arrived = () => {};
    const waiting = new Promise<void>((resolve) => {
        arrived = resolve;
    });
    await page.route(url, async (route) => {
        if (route.request().method() === 'PATCH') {
            const response = await route.fetch({ maxRedirects: 0 });
            arrived();
            await held;
            return route.fulfill({ response });
        }
        await route.continue();
    });
    try {
        await observeCatalogRendering(page, '#equipment-name');
        const button = page.getByRole('button', {
            name: 'Enregistrer les textes',
            exact: true,
        });
        const updated = page.waitForResponse(
            async (response) => {
                if (
                    new URL(response.url()).pathname !==
                        new URL(url).pathname + '/lecture' ||
                    response.status() !== 200
                )
                    return false;
                const data: unknown = await response.json();
                return (
                    typeof data === 'object' &&
                    data !== null &&
                    'equipmentVersion' in data &&
                    data.equipmentVersion === 2
                );
            },
            { timeout: 20_000 },
        );
        await button.click();
        await waiting;
        await updated;
        await expect(
            page.getByRole('heading', { name: nextName, exact: true }),
        ).toBeVisible();
        await expect(button).toBeDisabled();
        await expect(page.getByLabel('Nom *', { exact: true })).toBeDisabled();
        await expect(page.getByLabel('Nom *', { exact: true })).toHaveCSS(
            'opacity',
            '1',
        );
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'Enregistrement en cours…' }),
        ).toHaveText('Enregistrement en cours…');
        await expect(button.locator('span > span')).toHaveCSS(
            'animation-name',
            'none',
        );
        await expect(
            page.getByRole('heading', {
                name: 'Enregistrements à vérifier',
                exact: true,
            }),
        ).toHaveCount(0);
        release();
        await expect(
            page.getByRole('heading', { name: nextName, exact: true }),
        ).toBeVisible();
        await expect(button).toBeEnabled();
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'Enregistrement confirmé.' }),
        ).toBeVisible();
        const renders = await takeCatalogRendering(page);
        expect(
            renders.some((render) => render.pending || render.conflict),
            JSON.stringify(renders),
        ).toBe(false);
        expect(
            new Set(renders.map((render) => render.top)).size,
            JSON.stringify(renders),
        ).toBe(1);
    } finally {
        release();
        await page.unroute(url);
    }
});

test('a known empty search remains visible during a slow automatic check', async ({
    page,
}) => {
    test.setTimeout(45_000);
    await signIn(page);
    await page
        .getByLabel('Fragment du nom', { exact: true })
        .fill('No match ' + randomUUID());
    const checked = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname === '/gestion/materiels/lectures',
    );
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await checked;
    await expect(page.locator('ul[aria-busy]')).toHaveAttribute(
        'aria-busy',
        'false',
    );
    const empty = page
        .getByRole('status')
        .filter({ hasText: 'Aucun matériel ne correspond à ces critères.' });
    await expect(empty).toBeVisible();
    let release = () => {};
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    let arrived = () => {};
    const waiting = new Promise<void>((resolve) => {
        arrived = resolve;
    });
    const pattern = '**/gestion/materiels/lectures*';
    await page.route(pattern, async (route) => {
        arrived();
        await held;
        await route.continue();
    });
    try {
        await observeCatalogRendering(page, '#equipment-search');
        await waiting;
        await expect(page.locator('ul[aria-busy]')).toHaveAttribute(
            'aria-busy',
            'true',
        );
        await expect(empty).toBeVisible();
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'Actualisation en cours…' }),
        ).toHaveText('Actualisation en cours…');
        release();
        await expect(page.locator('ul[aria-busy]')).toHaveAttribute(
            'aria-busy',
            'false',
        );
        const renders = await takeCatalogRendering(page);
        expect(renders.every((render) => render.empty)).toBe(true);
        expect(new Set(renders.map((render) => render.top)).size).toBe(1);
    } finally {
        release();
        await page.unroute(pattern);
    }
});

test('an intervening authorized read does not consume the confirmation of a successful creation', async ({
    page,
}) => {
    await signIn(page);
    await page.route('**/gestion/materiels', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        const response = await route.fetch({ maxRedirects: 0 });
        expect([302, 303]).toContain(response.status());
        const read = await page.request.get('/gestion/materiels/lectures');
        expect(read.status()).toBe(200);
        await route.fulfill({ response });
    });
    const name = 'A03 receipt race ' + randomUUID();
    const url = await create(page, name);
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Enregistrement confirmé.' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', {
            name: 'Enregistrements à vérifier',
            exact: true,
        }),
    ).toHaveCount(0);
    await page.route(url, async (route) => {
        if (route.request().method() !== 'PATCH') return route.continue();
        const response = await route.fetch({ maxRedirects: 0 });
        expect([302, 303]).toContain(response.status());
        expect(
            (await page.request.get('/gestion/materiels/lectures')).status(),
        ).toBe(200);
        await route.fulfill({ response });
    });
    await page.getByLabel('Nom *', { exact: true }).fill(name + ' corrigé');
    await page
        .getByRole('button', { name: 'Enregistrer les textes', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: name + ' corrigé', exact: true }),
    ).toBeVisible();
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Enregistrement confirmé.' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', {
            name: 'Enregistrements à vérifier',
            exact: true,
        }),
    ).toHaveCount(0);
});

test('a newer external version before acknowledgement preserves the confirmed proposal until explicit resolution', async ({
    page,
    browser,
}) => {
    test.setTimeout(60_000);
    await signIn(page);
    const name = 'A03 acknowledged base ' + randomUUID();
    const url = await create(page, name);
    const otherContext = await browser.newContext();
    const other = await otherContext.newPage();
    let release = () => {};
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    let committed = () => {};
    const acquired = new Promise<void>((resolve) => {
        committed = resolve;
    });
    try {
        await signIn(other);
        await other.goto(url);
        await page.route(url, async (route) => {
            if (route.request().method() !== 'PATCH') return route.continue();
            const response = await route.fetch({ maxRedirects: 0 });
            expect([302, 303]).toContain(response.status());
            committed();
            await held;
            await route.fulfill({ response });
        });
        await page
            .getByLabel('Nom *', { exact: true })
            .fill('My acquired proposal');
        await page
            .getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            })
            .click();
        await acquired;
        await expect(other.getByLabel('Nom *', { exact: true })).toHaveValue(
            'My acquired proposal',
            { timeout: 20_000 },
        );
        await other
            .getByLabel('Nom *', { exact: true })
            .fill('A later external update');
        await other
            .getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            })
            .click();
        await expect(
            page.getByRole('heading', {
                name: 'A later external update',
                exact: true,
            }),
        ).toBeVisible({ timeout: 20_000 });
        release();
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'Enregistrement confirmé.' }),
        ).toBeVisible();
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'La fiche a changé ailleurs.' }),
        ).toBeVisible();
        await expect(page.getByLabel('Nom *', { exact: true })).toHaveValue(
            'My acquired proposal',
        );
        await page.unroute(url);
        await page
            .getByRole('button', {
                name: 'Reprendre les valeurs actuelles',
                exact: true,
            })
            .click();
        await expect(page.getByLabel('Nom *', { exact: true })).toHaveValue(
            'A later external update',
        );
        const next = page.waitForRequest(
            (request) => request.method() === 'PATCH' && request.url() === url,
        );
        await page
            .getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            })
            .click();
        const data: unknown = (await next).postDataJSON();
        if (
            typeof data !== 'object' ||
            data === null ||
            !('expectedVersion' in data) ||
            !('operationKey' in data) ||
            typeof data.operationKey !== 'string'
        )
            throw new Error('Missing operation reference in the new proposal');
        expect(data.expectedVersion).toBe(3);
        await expect(
            page.getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            }),
        ).toBeEnabled();
        const receipt = await page.request.get(
            '/gestion/materiels/operations?' +
                new URLSearchParams({
                    operationType: 'catalog.equipment.update_text',
                    operationKey: data.operationKey,
                }).toString(),
        );
        expect(receipt.status()).toBe(200);
        expect(await receipt.json()).toMatchObject({
            status: 'applied',
            receipt: {
                resourceId: new URL(url).pathname.split('/').pop(),
                resultVersion: 4,
            },
        });
    } finally {
        release();
        await page.unroute(url);
        await otherContext.close();
    }
});

test('manager creates, searches and edits real equipment without inventing availability', async ({
    page,
}) => {
    await signIn(page);
    const name = 'A03 %_ Électrique ' + randomUUID();
    const url = await create(page, name);
    await expect(
        page.getByText('Cette quantité est le total recensé.', {
            exact: false,
        }),
    ).toBeVisible();
    expect(await page.evaluate(() => 'catalogXss' in window)).toBe(false);
    await page.getByLabel('Nom *', { exact: true }).fill(name + ' corrigé');
    await page
        .getByRole('button', { name: 'Enregistrer les textes', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: name + ' corrigé', exact: true }),
    ).toBeVisible();
    await expect(page.getByLabel('Nom *', { exact: true })).toHaveValue(
        name + ' corrigé',
    );
    await page
        .getByRole('link', { name: 'Retour à la liste', exact: true })
        .click();
    await page
        .getByLabel('Fragment du nom', { exact: true })
        .fill(name.toLowerCase());
    await page
        .getByLabel('Catégorie exacte', { exact: true })
        .fill('A03 browser tools');
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(
        page.getByRole('link', { name: name + ' corrigé', exact: true }),
    ).toHaveAttribute('href', new URL(url).pathname);
    await page.getByLabel('Langue de l’interface').selectOption('en');
    await expect(
        page.getByRole('heading', {
            name: 'Equipment management',
            exact: true,
        }),
    ).toBeVisible();
    await expect(page.getByLabel('Name fragment', { exact: true })).toHaveValue(
        name.toLowerCase(),
    );
});

test('a real queued invalidation refreshes another browser without replacing its draft or focus', async ({
    page,
    browser,
}) => {
    await signIn(page);
    const name = 'A03 live ' + randomUUID();
    const url = await create(page, name);
    const otherContext = await browser.newContext();
    const other = await otherContext.newPage();
    try {
        await signIn(other);
        await other.goto(url);
        const field = other.getByLabel('Nom *', { exact: true });
        await field.fill('Unsaved local proposal');
        await field.focus();
        await page
            .getByLabel('Nom *', { exact: true })
            .fill(name + ' elsewhere');
        await page
            .getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            })
            .click();
        await expect(
            other.getByRole('heading', {
                name: name + ' elsewhere',
                exact: true,
            }),
        ).toBeVisible();
        await expect(field).toHaveValue('Unsaved local proposal');
        await expect(field).toBeFocused();
        await expect(
            other.getByText('La fiche a changé ailleurs.', { exact: false }),
        ).toBeVisible();
        await other
            .getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            })
            .click();
        await expect(
            other
                .getByRole('alert')
                .filter({ hasText: 'Cette fiche a été modifiée.' })
                .first(),
        ).toBeVisible();
        await expect(field).toHaveValue('Unsaved local proposal');
    } finally {
        await otherContext.close();
    }
});

test('a response lost after commit is verified without a duplicate and without persisting the form', async ({
    page,
}) => {
    await signIn(page);
    await page
        .getByRole('link', { name: 'Créer un matériel', exact: true })
        .click();
    const name = 'A03 lost response ' + randomUUID();
    await page.getByLabel('Nom *', { exact: true }).fill(name);
    await page
        .getByLabel('Catégorie *', { exact: true })
        .fill('A03 browser tools');
    await page.getByLabel('Quantité initiale *', { exact: true }).fill('3');
    let committed = false;
    await page.route('**/gestion/materiels', async (route) => {
        if (route.request().method() !== 'POST') {
            await route.continue();
            return;
        }
        const response = await route.fetch({ maxRedirects: 0 });
        expect(response.status()).toBe(302);
        committed = true;
        await route.abort('failed');
    });
    await page
        .getByRole('button', { name: 'Créer le matériel', exact: true })
        .click();
    await expect.poll(() => committed).toBe(true);
    await page.unroute('**/gestion/materiels');
    await expect(
        page.getByText('Connexion interrompue.', { exact: false }),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Vérifier mon accès', exact: true })
        .click();
    await expect(page.getByLabel('Nom *', { exact: true })).toHaveValue(name);
    const stored = await page.evaluate(() =>
        sessionStorage.getItem('epr.pendingCatalogOperations'),
    );
    expect(stored).not.toContain(name);
    expect(stored).not.toContain('A03 browser tools');
    await page
        .getByRole('button', { name: 'Vérifier le résultat', exact: true })
        .click();
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Cet enregistrement est confirmé.' }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Consulter', exact: true }).click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
});

test('a lost response survives reload as a minimal proof and prevents a second creation', async ({
    page,
}) => {
    await signIn(page);
    await page
        .getByRole('link', { name: 'Créer un matériel', exact: true })
        .click();
    const name = 'A03 reload ' + randomUUID();
    await page.getByLabel('Nom *', { exact: true }).fill(name);
    await page
        .getByLabel('Catégorie *', { exact: true })
        .fill('A03 browser tools');
    await page.getByLabel('Quantité initiale *', { exact: true }).fill('1');
    let committed = false;
    let sends = 0;
    await page.route('**/gestion/materiels', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        sends++;
        const response = await route.fetch({ maxRedirects: 0 });
        expect(response.status()).toBe(302);
        committed = true;
        await route.abort('failed');
    });
    await page
        .getByRole('button', { name: 'Créer le matériel', exact: true })
        .click();
    await expect.poll(() => committed).toBe(true);
    await page.unroute('**/gestion/materiels');
    await page.reload();
    await expect(page.getByLabel('Nom *', { exact: true })).toHaveValue('');
    await expect(
        page.getByRole('button', { name: 'Créer le matériel', exact: true }),
    ).toBeDisabled();
    await page
        .getByRole('button', { name: 'Vérifier le résultat', exact: true })
        .click();
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Cet enregistrement est confirmé.' }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Consulter', exact: true }).click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    expect(sends).toBe(1);
});

test('an uncommitted send resumes only after checking and with exactly the same body and key', async ({
    page,
}) => {
    await signIn(page);
    await page
        .getByRole('link', { name: 'Créer un matériel', exact: true })
        .click();
    const name = 'A03 resume ' + randomUUID();
    await page.getByLabel('Nom *', { exact: true }).fill(name);
    await page
        .getByLabel('Catégorie *', { exact: true })
        .fill('A03 browser tools');
    await page.getByLabel('Quantité initiale *', { exact: true }).fill('0');
    const bodies: unknown[] = [];
    await page.route('**/gestion/materiels', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        bodies.push(route.request().postDataJSON());
        if (bodies.length === 1) await route.abort('failed');
        else await route.continue();
    });
    await page
        .getByRole('button', { name: 'Créer le matériel', exact: true })
        .click();
    await expect(
        page.getByText('Connexion interrompue.', { exact: false }),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Vérifier mon accès', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Vérifier le résultat', exact: true })
        .click();
    await expect(page.getByText('Aucun reçu', { exact: false })).toBeVisible();
    expect(bodies).toHaveLength(1);
    await page
        .getByLabel('Nom *', { exact: true })
        .fill('This changed draft must not replace the held body');
    await page
        .getByRole('button', { name: 'Reprendre exactement', exact: false })
        .click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    expect(bodies).toHaveLength(2);
    expect(bodies[1]).toEqual(bodies[0]);
});

test('mobile validation remains understandable after a language change and foreign workshops stay private', async ({
    page,
    browser,
}) => {
    await page.setViewportSize({ width: 360, height: 780 });
    await signIn(page);
    const name = 'A03 private ' + randomUUID();
    const url = await create(page, name);
    await expect
        .poll(() =>
            page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        )
        .toBe(true);
    const otherContext = await browser.newContext();
    const other = await otherContext.newPage();
    try {
        await signIn(other, 'atelier-2-manager');
        await other.goto(url);
        await expect(
            other.getByRole('heading', { name, exact: true }),
        ).toHaveCount(0);
        const response = await other.request.get(url + '/lecture');
        expect(response.status()).toBe(404);
        expect(await response.text()).not.toContain(name);
    } finally {
        await otherContext.close();
    }
    await page
        .getByRole('link', { name: 'Retour à la liste', exact: true })
        .click();
    await page
        .getByRole('link', { name: 'Créer un matériel', exact: true })
        .click();
    await page.getByLabel('Nom *', { exact: true }).fill('Invalid total');
    await page
        .getByLabel('Catégorie *', { exact: true })
        .fill('A03 browser tools');
    await page.getByLabel('Quantité initiale *', { exact: true }).fill('-1');
    await page
        .getByRole('button', { name: 'Créer le matériel', exact: true })
        .click();
    await expect(
        page.getByLabel('Quantité initiale *', { exact: true }),
    ).toHaveAttribute('aria-invalid', 'true');
    await expect(
        page.getByLabel('Quantité initiale *', { exact: true }),
    ).toBeFocused();
    await page.getByLabel('Langue de l’interface').selectOption('en');
    await expect(page.locator('#equipment-total-error')).toContainText(
        'Enter a whole number without a fraction or exponent.',
    );
    await expect(
        page.getByLabel('Initial quantity *', { exact: true }),
    ).toHaveValue('-1');
});

test('the visible screen recovers within twenty seconds when its last real notification is dropped', async ({
    page,
    browser,
}) => {
    await signIn(page);
    const name = 'A03 missed signal ' + randomUUID();
    const url = await create(page, name);
    const otherContext = await browser.newContext();
    const other = await otherContext.newPage();
    let dropped = 0;
    let subscribed = false;
    await other.routeWebSocket(/.*/, (socket) => {
        const server = socket.connectToServer();
        server.onMessage((message) => {
            if (String(message).includes('subscription_succeeded'))
                subscribed = true;
            if (String(message).includes('epr.notification')) {
                dropped++;
                return;
            }
            socket.send(message);
        });
    });
    try {
        await signIn(other);
        const recovered = other.waitForResponse(
            (response) =>
                response.url().endsWith('/lecture') &&
                response.status() === 200,
        );
        await other.goto(url);
        await recovered;
        await expect.poll(() => subscribed).toBe(true);
        await expect(
            other.getByText(
                'Les informations affichées ne sont pas encore revérifiées.',
                { exact: true },
            ),
        ).toHaveCount(0);
        await expect(
            other.getByRole('heading', { name, exact: true }),
        ).toBeVisible();
        await page
            .getByLabel('Nom *', { exact: true })
            .fill(name + ' refreshed');
        const start = performance.now();
        await page
            .getByRole('button', {
                name: 'Enregistrer les textes',
                exact: true,
            })
            .click();
        await expect.poll(() => dropped).toBeGreaterThan(0);
        await expect(
            other.getByRole('heading', {
                name: name + ' refreshed',
                exact: true,
            }),
        ).toBeVisible({ timeout: 20_000 });
        expect(performance.now() - start).toBeLessThan(20_000);
    } finally {
        await otherContext.close();
    }
});

test('pagination retains both filters and a new search returns to the first page', async ({
    page,
}) => {
    await signIn(page);
    const common = 'A03 pages ' + randomUUID();
    await create(page, common + ' A');
    await page
        .getByRole('link', { name: 'Retour à la liste', exact: true })
        .click();
    await create(page, common + ' B');
    await page.goto(
        '/gestion/materiels?' +
            new URLSearchParams({
                search: common,
                category: 'A03 browser tools',
                perPage: '1',
            }).toString(),
    );
    await expect(
        page.getByRole('link', { name: common + ' A', exact: true }),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Page suivante', exact: true })
        .click();
    await expect(
        page.getByRole('link', { name: common + ' B', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByLabel('Fragment du nom', { exact: true }),
    ).toHaveValue(common);
    await expect(
        page.getByLabel('Catégorie exacte', { exact: true }),
    ).toHaveValue('A03 browser tools');
    await page
        .getByLabel('Fragment du nom', { exact: true })
        .fill(common + ' A');
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(page.getByText('Page 1', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('link', { name: common + ' A', exact: true }),
    ).toBeVisible();
});

test('checking a lost edit rereads the current equipment while keeping a later draft', async ({
    page,
}) => {
    await signIn(page);
    const original = 'A03 lost edit ' + randomUUID();
    const url = await create(page, original);
    await page.getByLabel('Nom *', { exact: true }).fill(original + ' saved');
    let committed = false;
    await page.route(url, async (route) => {
        if (route.request().method() !== 'PATCH') return route.continue();
        const response = await route.fetch({ maxRedirects: 0 });
        expect(response.status()).toBe(303);
        committed = true;
        await route.abort('failed');
    });
    await page
        .getByRole('button', { name: 'Enregistrer les textes', exact: true })
        .click();
    await expect.poll(() => committed).toBe(true);
    await page.unroute(url);
    await expect(
        page.getByText('Connexion interrompue.', { exact: false }),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Vérifier mon accès', exact: true })
        .click();
    await expect(page.locator('[inert]')).toHaveCount(0);
    await page
        .getByLabel('Nom *', { exact: true })
        .fill('Later local proposal');
    await expect(page.getByLabel('Nom *', { exact: true })).toHaveValue(
        'Later local proposal',
    );
    await page.getByLabel('Langue de l’interface').selectOption('en');
    await page
        .getByRole('button', { name: 'Check the result', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: original + ' saved', exact: true }),
    ).toBeVisible();
    await expect(page.getByLabel('Name *', { exact: true })).toHaveValue(
        'Later local proposal',
    );
    await expect(
        page.getByText('This equipment changed elsewhere.', { exact: false }),
    ).toBeVisible();
});
