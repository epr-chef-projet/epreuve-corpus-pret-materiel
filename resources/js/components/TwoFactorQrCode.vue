<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ svg: string }>();
const source = computed(
    () => `data:image/svg+xml;charset=utf-8,${encodeURIComponent(props.svg)}`,
);
</script>

<template>
    <img :src="source" alt="Two-factor authentication QR code" />
</template>
