<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { inject } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { accessLifecycleKey } from '@/modules/identity-access/public/access';
import { logout } from '@/routes';
import type { User } from '@/types';
defineProps<{ user: User }>();
const lifecycle = inject(accessLifecycleKey);
const { t } = useI18n();
function handleLogout() {
    lifecycle?.beginLogout();
    router.flushAll();
    router.clearHistory();
    router.post(
        logout.url(),
        {},
        { onSuccess: () => lifecycle?.finishLogout() },
    );
}
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal"
        ><div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-login="true" /></div
    ></DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuItem as-child
        ><button
            class="block w-full cursor-pointer"
            type="button"
            data-test="logout-button"
            @click="handleLogout"
        >
            <LogOut class="mr-2 h-4 w-4" />{{ t('identity.signOut') }}
        </button></DropdownMenuItem
    >
</template>
