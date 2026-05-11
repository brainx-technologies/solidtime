<script setup lang="ts">
import MultiselectDropdown from '@/packages/ui/src/Input/MultiselectDropdown.vue';
import { useMemberGroupsQuery } from '@/utils/useMemberGroupsQuery';
import type { MemberGroup } from '@/packages/api/src';

const { memberGroups } = useMemberGroupsQuery();

function getKeyFromItem(item: MemberGroup) {
    return item.id;
}

function getNameForItem(item: MemberGroup) {
    return item.name;
}

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <MultiselectDropdown
        search-placeholder="Search for a group..."
        :items="memberGroups"
        :get-key-from-item="getKeyFromItem"
        :get-name-for-item="getNameForItem"
        @submit="emit('submit')">
        <template #trigger>
            <slot name="trigger"></slot>
        </template>
    </MultiselectDropdown>
</template>
