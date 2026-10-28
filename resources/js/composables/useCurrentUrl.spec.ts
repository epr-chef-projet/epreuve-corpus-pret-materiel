import { describe, expect, expectTypeOf, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/catalog?filter=active' }),
}));

import { useCurrentUrl } from '@/composables/useCurrentUrl';

describe('current URL selection', () => {
    const current = useCurrentUrl();

    it('compares paths for current and absolute URLs', () => {
        expect(current.currentUrl.value).toBe('/catalog');
        expect(current.isCurrentUrl('/catalog')).toBe(true);
        expect(
            current.isCurrentUrl('https://example.test/catalog?other=1'),
        ).toBe(true);
        expect(current.isCurrentUrl('/elsewhere')).toBe(false);
        expect(current.isCurrentUrl('http://[')).toBe(false);
    });

    it('keeps explicit parent matching distinct from exact matching', () => {
        expect(current.isCurrentUrl('/catalog', '/catalog/42')).toBe(false);
        expect(current.isCurrentOrParentUrl('/catalog', '/catalog/42')).toBe(
            true,
        );
    });

    it('preserves selected values, null default and falsy alternatives', () => {
        const selected = { value: 42 };
        expect(current.whenCurrentUrl('/catalog', selected)).toBe(selected);
        expect(current.whenCurrentUrl('/elsewhere', selected)).toBeNull();
        expect(current.whenCurrentUrl('/elsewhere', selected, 0)).toBe(0);
        expect(current.whenCurrentUrl('/elsewhere', selected, false)).toBe(
            false,
        );
        expect(current.whenCurrentUrl('/elsewhere', selected, '')).toBe('');
        expectTypeOf(
            current.whenCurrentUrl('/catalog', selected),
        ).toEqualTypeOf<typeof selected | null>();
        expectTypeOf(
            current.whenCurrentUrl('/elsewhere', selected, 0),
        ).toEqualTypeOf<typeof selected | number>();
    });
});
