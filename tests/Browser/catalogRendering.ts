import type { Page } from '@playwright/test';

export type CatalogRender = {
    top: number | null;
    pending: boolean;
    conflict: boolean;
    refreshing: boolean;
    empty: boolean;
};

type Observation = {
    renders: CatalogRender[];
    stop: () => void;
};

type ObservedWindow = Window & { catalogObservation?: Observation };

export async function observeCatalogRendering(page: Page, selector: string) {
    await page.evaluate((anchor) => {
        const target = window as ObservedWindow;
        target.catalogObservation?.stop();
        const renders: CatalogRender[] = [];
        let previous = '';
        let frame = 0;
        const sample = () => {
            const element = document.querySelector<HTMLElement>(anchor);
            const visible = Array.from(
                document.querySelectorAll<HTMLElement>(
                    'main [role="status"], main [aria-labelledby="pending-title"]',
                ),
            )
                .filter((node) =>
                    node.checkVisibility({ visibilityProperty: true }),
                )
                .map((node) => node.innerText);
            const render: CatalogRender = {
                top: element
                    ? Math.round(element.getBoundingClientRect().top + scrollY)
                    : null,
                pending:
                    document
                        .querySelector<HTMLElement>(
                            '[aria-labelledby="pending-title"]',
                        )
                        ?.checkVisibility({ visibilityProperty: true }) ??
                    false,
                conflict: visible.some(
                    (text) =>
                        text.includes('La fiche a changé ailleurs') ||
                        text.includes('This equipment changed elsewhere'),
                ),
                refreshing: visible.some((text) =>
                    text.includes('Actualisation en cours'),
                ),
                empty: visible.some((text) =>
                    text.includes('Aucun matériel ne correspond'),
                ),
            };
            const signature = JSON.stringify(render);
            if (signature !== previous) {
                renders.push(render);
                previous = signature;
            }
        };
        const observer = new MutationObserver(sample);
        observer.observe(document.body, {
            attributes: true,
            childList: true,
            subtree: true,
            characterData: true,
        });
        const tick = () => {
            sample();
            frame = requestAnimationFrame(tick);
        };
        tick();
        target.catalogObservation = {
            renders,
            stop: () => {
                observer.disconnect();
                cancelAnimationFrame(frame);
            },
        };
    }, selector);
}

export async function takeCatalogRendering(
    page: Page,
): Promise<CatalogRender[]> {
    return page.evaluate(() => {
        const target = window as ObservedWindow;
        const observation = target.catalogObservation;
        if (!observation)
            throw new Error('Catalog observation was not started');
        observation.stop();
        delete target.catalogObservation;
        return observation.renders;
    });
}
