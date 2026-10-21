import type { Page } from '@playwright/test';

export type ButtonRender = {
    width: number;
    height: number;
    top: number;
    left: number;
    labelOffset: number;
    spinner: boolean;
    busy: boolean;
};

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

type ObservedWindow = Window & {
    catalogObservation?: Observation;
    buttonObservation?: { renders: ButtonRender[]; stop: () => void };
};

export async function observeButtonRendering(page: Page, selector: string) {
    await page.evaluate((selector) => {
        const target = window as ObservedWindow;
        target.buttonObservation?.stop();
        const button = document.querySelector<HTMLElement>(selector);
        if (!button) throw new Error('Button observation target is missing');
        const renders: ButtonRender[] = [];
        let previous = '';
        let frame = 0;
        const sample = () => {
            if (!button.isConnected) return;
            const bounds = button.getBoundingClientRect();
            const texts = document.createTreeWalker(
                button,
                NodeFilter.SHOW_TEXT,
            );
            let text: Node | null;
            let label: DOMRect | null = null;
            while ((text = texts.nextNode())) {
                if (!text.textContent?.trim()) continue;
                const range = document.createRange();
                range.selectNodeContents(text);
                label = range.getBoundingClientRect();
                break;
            }
            const round = (value: number) => Math.round(value * 100) / 100;
            const render: ButtonRender = {
                width: round(bounds.width),
                height: round(bounds.height),
                top: round(bounds.top + scrollY),
                left: round(bounds.left + scrollX),
                labelOffset: label
                    ? round(
                          label.left +
                              label.width / 2 -
                              bounds.left -
                              bounds.width / 2,
                      )
                    : Infinity,
                spinner: Array.from(
                    button.querySelectorAll('svg, .animate-spin'),
                ).some((icon) =>
                    icon.checkVisibility({
                        opacityProperty: true,
                        visibilityProperty: true,
                    }),
                ),
                busy: button.getAttribute('aria-busy') === 'true',
            };
            const signature = JSON.stringify(render);
            if (signature !== previous) {
                renders.push(render);
                previous = signature;
            }
        };
        const observer = new MutationObserver(sample);
        observer.observe(button, {
            attributes: true,
            childList: true,
            characterData: true,
            subtree: true,
        });
        const tick = () => {
            sample();
            frame = requestAnimationFrame(tick);
        };
        tick();
        target.buttonObservation = {
            renders,
            stop: () => {
                observer.disconnect();
                cancelAnimationFrame(frame);
            },
        };
    }, selector);
}

export async function takeButtonRendering(page: Page): Promise<ButtonRender[]> {
    return page.evaluate(() => {
        const target = window as ObservedWindow;
        const observation = target.buttonObservation;
        if (!observation) throw new Error('Button observation was not started');
        observation.stop();
        delete target.buttonObservation;
        return observation.renders;
    });
}

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
