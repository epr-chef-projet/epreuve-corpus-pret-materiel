<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

const page = usePage();
defineOptions({
    name: 'DashboardPage',
    layout: {
        breadcrumbs: [{ title: 'Accès à l’atelier', href: dashboard() }],
    },
});
</script>

<template>
    <div class="flex flex-col gap-4 p-4">
        <Head title="Accès à l’atelier" />
        <h1 class="text-xl font-semibold">Accès à l’atelier</h1>
        <p v-if="page.props.auth.user">
            {{ page.props.auth.user.workshopName }} —
            {{ page.props.auth.user.displayName }} ({{
                page.props.auth.user.role
            }})
        </p>
        <p role="status">
            Votre accès est actif. Le catalogue et les prêts seront disponibles
            dans les prochaines étapes.
        </p>
    </div>
</template>
