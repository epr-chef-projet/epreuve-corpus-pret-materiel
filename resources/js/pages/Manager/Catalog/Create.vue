<script setup lang="ts">
defineOptions({ name: 'ManagerEquipmentCreatePage' });
import type { IssueData } from '@/types/generated/types/App/Shared/Errors';
defineProps<{ issue?: IssueData | null }>();
import { EquipmentCreate } from '@/modules/catalog/public/equipment';
</script>

<template>
    <EquipmentCreate :issue="issue" />
</template>
