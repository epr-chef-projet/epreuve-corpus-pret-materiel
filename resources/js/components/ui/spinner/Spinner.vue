<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { LoaderIcon } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
defineOptions({ name: 'UiSpinner' });
</script>

<template>
    <LoaderIcon
        aria-hidden="true"
        :class="
            cn(
                'size-4 animate-spin motion-reduce:animate-none [&_path:nth-child(2)]:opacity-90 [&_path:nth-child(3)]:opacity-80 [&_path:nth-child(4)]:opacity-65 [&_path:nth-child(5)]:opacity-50 [&_path:nth-child(6)]:opacity-35 [&_path:nth-child(7)]:opacity-20 [&_path:nth-child(8)]:opacity-10',
                props.class,
            )
        "
    />
</template>
