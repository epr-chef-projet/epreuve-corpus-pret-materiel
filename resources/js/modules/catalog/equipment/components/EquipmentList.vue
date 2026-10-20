<script setup lang="ts">
import type { ManagementPageData as EquipmentPage } from '@/types/generated/types/App/Modules/Catalog/Equipment/UseCases/ReadEquipment';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { fieldError } from '@/shared/i18n/fieldError';
import { computed, reactive, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, show } from '@/routes/catalog';
import { manageCatalog } from '@/routes';
import type { EquipmentQuery } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import { useCatalogProjection } from '../composables/useCatalogProjection';
import { readEquipmentPage } from '../requests/readEquipment';
import PendingOperations from './PendingOperations.vue';
import EquipmentReadStatus from './EquipmentReadStatus.vue';
const props = defineProps<{
    initialPage: EquipmentPage;
    query: EquipmentQuery;
}>();
const composer = useI18n();
const { t } = composer;
const page = usePage();
const error = (name: string) =>
    fieldError(
        page.props.validationPresentation,
        name,
        page.props.errors[name],
        composer,
    );
const fields = reactive({
    search: props.query.search,
    category: props.query.category ?? '',
});
watch(
    () => props.query,
    (value) => {
        fields.search = value.search;
        fields.category = value.category ?? '';
    },
);
const projection = useCatalogProjection(
    toRef(props, 'initialPage'),
    () => JSON.stringify(props.query),
    (signal) => readEquipmentPage(props.query, signal),
);
const rows = computed(() => projection.data.value.items);
function visit(page = 1) {
    router.get(
        manageCatalog.url(),
        {
            search: fields.search,
            category: fields.category || undefined,
            page,
            perPage: props.query.perPage,
        },
        { preserveState: true, preserveScroll: true },
    );
}
function clear() {
    fields.search = '';
    fields.category = '';
    visit();
}
</script>

<template>
    <section class="flex flex-col gap-5 p-4 sm:p-6">
        <Head :title="t('catalog.title')" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ t('catalog.title') }}</h1>
            <Button as-child
                ><Link :href="create()">{{ t('catalog.create') }}</Link></Button
            >
        </div>
        <p class="text-sm text-muted-foreground">{{ t('catalog.partial') }}</p>
        <PendingOperations />
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="visit()">
            <div class="grid min-w-0 flex-1 gap-2">
                <Label for="equipment-search">{{ t('catalog.search') }}</Label
                ><Input
                    id="equipment-search"
                    v-model="fields.search"
                    maxlength="120"
                    :aria-invalid="Boolean(page.props.errors.search)"
                    aria-describedby="equipment-search-error"
                /><InputError
                    id="equipment-search-error"
                    :message="error('search')"
                />
            </div>
            <div class="grid min-w-0 flex-1 gap-2">
                <Label for="equipment-category">{{
                    t('catalog.categoryFilter')
                }}</Label
                ><Input
                    id="equipment-category"
                    v-model="fields.category"
                    maxlength="80"
                    :aria-invalid="Boolean(page.props.errors.category)"
                    aria-describedby="equipment-category-filter-error"
                /><InputError
                    id="equipment-category-filter-error"
                    :message="error('category')"
                />
            </div>
            <Button type="submit">{{ t('catalog.searchSubmit') }}</Button
            ><Button type="button" variant="outline" @click="clear">{{
                t('catalog.clear')
            }}</Button>
        </form>
        <p v-if="page.props.errors.catalog" role="alert">
            {{ error('catalog') }}
        </p>
        <EquipmentReadStatus
            :stale="projection.stale.value"
            :refreshing="projection.refreshing.value"
        />
        <div v-if="projection.failed.value" role="alert">
            <p>{{ t('catalog.readFailed') }}</p>
            <Button variant="outline" @click="projection.refresh">{{
                t('catalog.retryRead')
            }}</Button>
        </div>
        <p v-if="rows.length === 0 && !projection.failed.value" role="status">
            {{
                t(
                    query.search || query.category
                        ? 'catalog.noResults'
                        : 'catalog.empty',
                )
            }}
        </p>
        <ul
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            :aria-busy="projection.busy.value"
        >
            <li
                v-for="equipment in rows"
                :key="equipment.equipmentId"
                class="flex min-w-0 flex-col gap-3 rounded-lg border p-4"
            >
                <div
                    class="grid min-h-24 place-items-center rounded bg-muted text-sm text-muted-foreground"
                >
                    {{ t('catalog.noImage') }}
                </div>
                <h2 class="text-lg font-medium break-words">
                    <Link
                        class="underline"
                        :href="show(equipment.equipmentId)"
                        >{{ equipment.name }}</Link
                    >
                </h2>
                <p class="break-words">{{ equipment.category }}</p>
                <p>
                    {{ t('catalog.total') }} :
                    {{ equipment.totalQuantity.toLocaleString($i18n.locale) }}
                </p>
                <p>
                    {{
                        t(
                            equipment.status === 'active'
                                ? 'catalog.active'
                                : 'catalog.archived',
                        )
                    }}
                </p>
            </li>
        </ul>
        <p class="text-sm text-muted-foreground">
            {{ t('catalog.totalNotice') }}
        </p>
        <nav
            class="flex flex-wrap items-center gap-3"
            :aria-label="t('catalog.title')"
        >
            <Button
                variant="outline"
                :disabled="query.page <= 1"
                @click="visit(query.page - 1)"
                >{{ t('catalog.previous') }}</Button
            >
            <p>{{ t('catalog.page', { page: query.page }) }}</p>
            <Button
                variant="outline"
                :disabled="!projection.data.value.hasMore"
                @click="visit(query.page + 1)"
                >{{ t('catalog.next') }}</Button
            >
        </nav>
    </section>
</template>
