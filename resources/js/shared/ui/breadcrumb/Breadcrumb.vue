<script lang="ts" setup>
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
defineOptions({ name: 'UiBreadcrumb' });
</script>

<template>
    <nav aria-label="breadcrumb" data-slot="breadcrumb" :class="props.class">
        <slot />
    </nav>
</template>
