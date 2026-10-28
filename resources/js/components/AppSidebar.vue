<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/shared/ui/sidebar';
import { catalogue, ownRequests, teamRequests, manageCatalog } from '@/routes';
import { useAccessStore } from '@/modules/identity-access/public/access';
import type { NavItem } from '@/types';
const access = useAccessStore();
const blocked = computed(
    () => access.status !== 'active' || access.stale || access.confirming,
);
const { t } = useI18n();
const items = computed<NavItem[]>(() => {
    const role = access.context?.user?.role;
    const result: NavItem[] = [
        { title: t('identity.catalog'), href: catalogue(), icon: LayoutGrid },
    ];
    if (role === 'borrower')
        result.push({
            title: t('identity.ownRequests'),
            href: ownRequests(),
            icon: LayoutGrid,
        });
    if (role === 'staff' || role === 'manager')
        result.unshift({
            title: t('identity.teamRequests'),
            href: teamRequests(),
            icon: LayoutGrid,
        });
    if (role === 'manager')
        result.push({
            title: t('identity.management'),
            href: manageCatalog(),
            icon: LayoutGrid,
        });
    return result;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader :inert="blocked"
            ><SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton size="lg" as-child
                        ><Link
                            :href="
                                access.context?.user?.role === 'borrower'
                                    ? catalogue()
                                    : teamRequests()
                            "
                            ><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu
        ></SidebarHeader>
        <SidebarContent :inert="blocked"
            ><NavMain :items="items"
        /></SidebarContent>
        <SidebarFooter><NavUser /></SidebarFooter>
    </Sidebar>
</template>
