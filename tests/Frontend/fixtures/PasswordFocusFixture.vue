<script setup lang="ts">
import { useTemplateRef } from 'vue';
import PasswordInput from '@/components/PasswordInput.vue';
import type { InputHandle } from '@/shared/ui/input/types';

const field = useTemplateRef<InputHandle>('field');
function focusPassword() {
    field.value?.focus();
}
</script>

<template>
    <PasswordInput id="password-fixture" ref="field" name="password-fixture" />
    <button data-test="focus-password" @click="focusPassword">
        Focus password
    </button>
</template>
