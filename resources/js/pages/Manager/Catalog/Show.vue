<script setup lang="ts">
defineOptions({ name: 'ManagerEquipmentShowPage' });
import { EquipmentDetails } from '@/modules/catalog/public/equipment';
import type { EquipmentData } from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type { CommandReceipt } from '@/types/generated/types/App/Infrastructure/Operations';
import type { IssueData } from '@/types/generated/types/App/Shared/Errors';
defineProps<{
    equipment: EquipmentData;
    commandReceipt?: CommandReceipt | null;
    issue?: IssueData | null;
}>();
</script>

<template>
    <EquipmentDetails
        :initial-equipment="equipment"
        :receipt="commandReceipt"
        :issue="issue"
    />
</template>
