import type { Directive } from 'vue';

export const focus: Directive<HTMLElement, boolean | undefined> = {
    mounted(element, binding) {
        if (binding.value !== false) element.focus();
    },
};
