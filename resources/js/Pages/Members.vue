<script setup lang="ts">
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/16/solid';
import { UserGroupIcon } from '@heroicons/vue/20/solid';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import { TabBar, TabBarItem } from '@/packages/ui/src';
import { ref } from 'vue';
import MemberTable from '@/Components/Common/Member/MemberTable.vue';
import MemberInviteModal from '@/Components/Common/Member/MemberInviteModal.vue';
import type { Role } from '@/types/jetstream';
import PageTitle from '@/Components/Common/PageTitle.vue';
import InvitationTable from '@/Components/Common/Invitation/InvitationTable.vue';
import MemberGroupTable from '@/Components/Common/MemberGroup/MemberGroupTable.vue';
import MemberGroupCreateModal from '@/Components/Common/MemberGroup/MemberGroupCreateModal.vue';
import MemberTimeEntryEditOverrides from '@/Pages/Members/Partials/MemberTimeEntryEditOverrides.vue';
import MemberTimeEntryEditOverrideCreateModal from '@/Components/Common/Member/MemberTimeEntryEditOverrideCreateModal.vue';
import {
    canCreateInvitations,
    canManageMemberTimeEntryOverrides,
    canUpdateMembers,
} from '@/utils/permissions';
import { useStorage } from '@vueuse/core';
import type { SortColumn, SortDirection } from '@/Components/Common/Member/MemberTable.vue';

const inviteMember = ref(false);
const createGroup = ref(false);
const createOverride = ref(false);
const editOverridesRef = ref<InstanceType<typeof MemberTimeEntryEditOverrides> | null>(null);

function onOverrideCreated() {
    editOverridesRef.value?.refresh();
}

defineProps<{
    availableRoles: Role[];
}>();

const activeTab = ref<'all' | 'invitations' | 'groups' | 'overrides'>('all');

interface MemberTableState {
    sortColumn: SortColumn;
    sortDirection: SortDirection;
}

const tableState = useStorage<MemberTableState>(
    'member-table-state',
    {
        sortColumn: 'name',
        sortDirection: 'asc',
    },
    undefined,
    { mergeDefaults: true }
);

function handleSort(column: SortColumn, direction: SortDirection) {
    tableState.value.sortColumn = column;
    tableState.value.sortDirection = direction;
}
</script>

<template>
    <AppLayout title="Members" data-testid="members_view">
        <MainContainer
            class="py-5 border-b border-default-background-separator flex justify-between items-center">
            <div class="flex items-center space-x-4 sm:space-x-6">
                <PageTitle :icon="UserGroupIcon" title="Members"> </PageTitle>
                <TabBar v-model="activeTab">
                    <TabBarItem value="all">All</TabBarItem>
                    <TabBarItem value="groups">Groups</TabBarItem>
                    <TabBarItem value="invitations">Invitations</TabBarItem>
                    <TabBarItem v-if="canManageMemberTimeEntryOverrides()" value="overrides"
                        >Edit overrides</TabBarItem
                    >
                </TabBar>
            </div>
            <div class="flex items-center space-x-3">
                <SecondaryButton
                    v-if="activeTab === 'groups' && canUpdateMembers()"
                    :icon="PlusIcon"
                    @click="createGroup = true"
                    >Create group</SecondaryButton
                >
                <SecondaryButton
                    v-if="activeTab === 'overrides' && canManageMemberTimeEntryOverrides()"
                    :icon="PlusIcon"
                    data-testid="members_add_override"
                    @click="createOverride = true"
                    >Add override</SecondaryButton
                >
                <SecondaryButton
                    v-if="
                        (activeTab === 'all' || activeTab === 'invitations') && canCreateInvitations()
                    "
                    :icon="PlusIcon"
                    @click="inviteMember = true"
                    >Invite member</SecondaryButton
                >
            </div>
            <MemberInviteModal
                v-model:show="inviteMember"
                :available-roles="availableRoles"
                @close="activeTab = 'invitations'"></MemberInviteModal>
            <MemberGroupCreateModal v-model:show="createGroup"></MemberGroupCreateModal>
            <MemberTimeEntryEditOverrideCreateModal
                v-model:show="createOverride"
                @created="onOverrideCreated"></MemberTimeEntryEditOverrideCreateModal>
        </MainContainer>
        <MemberTable
            v-if="activeTab === 'all'"
            :sort-column="tableState.sortColumn"
            :sort-direction="tableState.sortDirection"
            @sort="handleSort"></MemberTable>
        <MemberGroupTable v-if="activeTab === 'groups'"></MemberGroupTable>
        <InvitationTable v-if="activeTab === 'invitations'"></InvitationTable>
        <MemberTimeEntryEditOverrides
            v-if="activeTab === 'overrides'"
            ref="editOverridesRef"></MemberTimeEntryEditOverrides>
    </AppLayout>
</template>
