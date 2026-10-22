<script setup lang="ts">
import type { ManagementPageData as EquipmentPage } from '@/types/generated/types/App/Modules/Catalog/Equipment/UseCases/ReadEquipment';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { fieldError } from '@/shared/i18n/fieldError';
import { computed, onScopeDispose, reactive, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button, LoadingButton } from '@/components/ui/button';
import { useAccessStore } from '@/modules/identity-access/public/access';
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
const access = useAccessStore();
const generation = access.generation;
const activeVisit = ref<'search' | 'clear' | 'previous' | 'next' | null>(null);
let visitId = 0;
const disown = access.own(() => {
    visitId++;
    activeVisit.value = null;
});
onScopeDispose(() => {
    visitId++;
    disown();
});
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
    (incoming, current) =>
        incoming.items.every((item) => {
            const known = current.items.find(
                (row) => row.equipmentId === item.equipmentId,
            );
            return !known || item.equipmentVersion >= known.equipmentVersion;
        }),
    // Row versions cannot order collection membership, including an empty page.
    () => false,
);
const rows = computed(() => projection.data.value.items);
function visit(
    page = 1,
    action: NonNullable<typeof activeVisit.value> = 'search',
) {
    if (activeVisit.value || generation !== access.generation) return;
    const criteria =
        action === 'previous' || action === 'next' ? props.query : fields;
    const id = ++visitId;
    activeVisit.value = action;
    router.get(
        manageCatalog.url(),
        {
            search: criteria.search,
            category: criteria.category || undefined,
            page,
            perPage: props.query.perPage,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                if (id === visitId && generation === access.generation)
                    activeVisit.value = null;
            },
        },
    );
}
function clear() {
    if (activeVisit.value) return;
    fields.search = '';
    fields.category = '';
    visit(1, 'clear');
}
</script>

<template>
    <section class="flex flex-col gap-5 p-4 sm:p-6">
        <Head :title="t('catalog.title')" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="relative pr-7">
                <h1 class="text-2xl font-semibold">{{ t('catalog.title') }}</h1>
                <EquipmentReadStatus
                    :verifying="
                        projection.verifying.value &&
                        !activeVisit &&
                        !projection.manualRefreshing.value
                    "
                />
            </div>
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
                    :disabled="activeVisit !== null"
                    class="disabled:opacity-100"
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
                    :disabled="activeVisit !== null"
                    class="disabled:opacity-100"
                    maxlength="80"
                    :aria-invalid="Boolean(page.props.errors.category)"
                    aria-describedby="equipment-category-filter-error"
                /><InputError
                    id="equipment-category-filter-error"
                    :message="error('category')"
                />
            </div>
            <LoadingButton
                type="submit"
                :label="t('catalog.searchSubmit')"
                :busy-label="t('catalog.loading')"
                :busy="activeVisit === 'search'"
                :disabled="activeVisit !== null"
                :class="{ 'disabled:opacity-100': activeVisit !== null }"
            />
            <LoadingButton
                type="button"
                variant="outline"
                :label="t('catalog.clear')"
                :busy-label="t('catalog.loading')"
                :busy="activeVisit === 'clear'"
                :disabled="activeVisit !== null"
                :class="{ 'disabled:opacity-100': activeVisit !== null }"
                @click="clear"
            />
        </form>
        <p v-if="page.props.errors.catalog" role="alert">
            {{ error('catalog') }}
        </p>
        <div v-if="projection.failed.value" role="alert">
            <p>{{ t('catalog.readFailed') }}</p>
            <LoadingButton
                variant="outline"
                :label="t('catalog.retryRead')"
                :busy-label="t('catalog.loading')"
                :busy="projection.manualRefreshing.value"
                :disabled="projection.manualRefreshing.value"
                @click="projection.refresh"
            />
        </div>
        <p
            v-else-if="projection.expired.value"
            role="status"
            class="text-sm text-muted-foreground"
        >
            {{ t('catalog.stale') }}
        </p>
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
            <LoadingButton
                variant="outline"
                :disabled="query.page <= 1 || activeVisit !== null"
                :class="{ 'disabled:opacity-100': activeVisit !== null }"
                :label="t('catalog.previous')"
                :busy-label="t('catalog.loading')"
                :busy="activeVisit === 'previous'"
                @click="visit(query.page - 1, 'previous')"
            />
            <p>{{ t('catalog.page', { page: query.page }) }}</p>
            <LoadingButton
                variant="outline"
                :disabled="
                    !projection.data.value.hasMore || activeVisit !== null
                "
                :class="{ 'disabled:opacity-100': activeVisit !== null }"
                :label="t('catalog.next')"
                :busy-label="t('catalog.loading')"
                :busy="activeVisit === 'next'"
                @click="visit(query.page + 1, 'next')"
            />
        </nav>
    </section>
</template>
