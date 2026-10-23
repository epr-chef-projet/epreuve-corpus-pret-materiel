import type { InjectionKey } from 'vue';
import type { AccessLifecycle } from './lifecycle';

export const accessLifecycleKey: InjectionKey<AccessLifecycle> = Symbol(
    'identity-access.lifecycle',
);
