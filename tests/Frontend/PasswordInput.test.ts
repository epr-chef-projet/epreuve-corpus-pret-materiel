import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import PasswordFocusFixture from './fixtures/PasswordFocusFixture.vue';
import { focus } from '@/shared/directives/focus';

describe('password input public focus and visibility', () => {
    const wrappers: Array<ReturnType<typeof mount>> = [];
    afterEach(() => {
        for (const wrapper of wrappers.splice(0)) wrapper.unmount();
    });

    it('focuses the native field through the typed public handle', async () => {
        const wrapper = mount(PasswordFocusFixture, {
            attachTo: document.body,
            global: { directives: { focus } },
        });
        wrappers.push(wrapper);
        await wrapper.get('[data-test="focus-password"]').trigger('click');
        expect(document.activeElement).toBe(wrapper.get('input').element);
        expect(wrapper.get('input').attributes('name')).toBe(
            'password-fixture',
        );
    });

    it('keeps the value and label while toggling visibility', async () => {
        const wrapper = mount(PasswordFocusFixture, {
            global: { directives: { focus } },
        });
        wrappers.push(wrapper);
        const field = wrapper.get<HTMLInputElement>('input');
        await field.setValue('synthetic-fixture');
        expect(field.attributes('type')).toBe('password');
        await wrapper
            .get('button[aria-label="Show password"]')
            .trigger('click');
        expect(field.attributes('type')).toBe('text');
        expect(
            wrapper.find('button[aria-label="Hide password"]').exists(),
        ).toBe(true);
        expect(field.element.value).toBe('synthetic-fixture');
        await wrapper
            .get('button[aria-label="Hide password"]')
            .trigger('click');
        expect(field.attributes('type')).toBe('password');
    });
});
