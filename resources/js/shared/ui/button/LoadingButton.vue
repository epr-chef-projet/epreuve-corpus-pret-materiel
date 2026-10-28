<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { toRef } from 'vue';
import type { ButtonVariants } from '.';
import { useActivityIndicator } from '@/composables/useActivityIndicator';
import { Spinner } from '@/shared/ui/spinner';
import { cn } from '@/lib/utils';
import Button from './Button.vue';

const props = defineProps<{
    label: string;
    busyLabel: string;
    busy: boolean;
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
}>();
defineOptions({ name: 'UiLoadingButton' });
const indicator = useActivityIndicator(toRef(props, 'busy'));
</script>

<template>
    <Button
        :variant="variant"
        :size="size"
        :aria-label="label"
        :aria-description="busy ? busyLabel : undefined"
        :aria-busy="busy"
        :class="cn('relative', busy && 'disabled:opacity-100', props.class)"
    >
        <span
            class="text-center"
            :class="{ 'opacity-0': indicator.visible.value }"
            aria-hidden="true"
            >{{ label }}</span
        >
        <span
            v-if="indicator.visible.value"
            class="pointer-events-none absolute inset-0 grid place-items-center"
            aria-hidden="true"
            ><Spinner
        /></span>
    </Button>
</template>
