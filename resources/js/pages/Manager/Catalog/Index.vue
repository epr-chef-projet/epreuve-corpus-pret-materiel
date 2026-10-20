<script setup lang="ts">
import type { ManagementPageData as EquipmentPage } from '@/types/generated/types/App/Modules/Catalog/Equipment/UseCases/ReadEquipment';
defineOptions({ name: 'ManagerEquipmentIndexPage' });
import { EquipmentList } from '@/modules/catalog/public/equipment';
import type { EquipmentQuery } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
defineProps<{ catalogPage: EquipmentPage; catalogQuery: EquipmentQuery }>();
</script>

<template>
    <EquipmentList :initial-page="catalogPage" :query="catalogQuery" />
</template>
