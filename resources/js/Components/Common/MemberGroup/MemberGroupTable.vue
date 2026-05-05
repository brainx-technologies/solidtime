<script setup lang="ts">
import { computed, ref } from 'vue';
import { useMemberGroupsQuery } from '@/utils/useMemberGroupsQuery';
import { useMembersQuery } from '@/utils/useMembersQuery';
import MemberGroupTableRow from '@/Components/Common/MemberGroup/MemberGroupTableRow.vue';
import MemberGroupTableHeading from '@/Components/Common/MemberGroup/MemberGroupTableHeading.vue';
import MemberGroupCreateModal from '@/Components/Common/MemberGroup/MemberGroupCreateModal.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { PlusIcon } from '@heroicons/vue/16/solid';
import { UserGroupIcon } from '@heroicons/vue/20/solid';
import type { Member, MemberGroup } from '@/packages/api/src';
import { canUpdateMembers } from '@/utils/permissions';

const { memberGroups, isLoading } = useMemberGroupsQuery();
const { members } = useMembersQuery();

const search = ref('');
const showCreateGroupModal = ref(false);

const groupsWithMembers = computed<Array<MemberGroup & { groupMembers: Member[] }>>(() => {
    return memberGroups.value.map((group) => {
        const groupMembers = members.value.filter((member) =>
            (member.groups ?? []).some((g) => g.id === group.id)
        );
        return { ...group, groupMembers };
    });
});

const filteredGroups = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return groupsWithMembers.value;
    return groupsWithMembers.value.filter((group) => {
        if (group.name.toLowerCase().includes(term)) return true;
        return group.groupMembers.some((member) =>
            member.name.toLowerCase().includes(term)
        );
    });
});

const sortedGroups = computed(() =>
    [...filteredGroups.value].sort((a, b) => a.name.localeCompare(b.name))
);
</script>

<template>
    <MemberGroupCreateModal v-model:show="showCreateGroupModal"></MemberGroupCreateModal>
    <div
        class="flex flex-wrap items-center gap-3 px-4 sm:px-6 lg:px-8 3xl:px-12 py-3 border-b border-default-background-separator">
        <div class="relative">
            <MagnifyingGlassIcon
                class="w-4 h-4 text-icon-default absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <TextInput
                v-model="search"
                type="search"
                placeholder="Search by group or member name"
                class="!pl-9 min-w-[300px]"
                data-testid="member_group_search" />
        </div>
    </div>
    <div class="flow-root max-w-[100vw] overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div
                data-testid="member_group_table"
                class="grid min-w-full"
                style="grid-template-columns: 1fr 3fr 130px">
                <MemberGroupTableHeading></MemberGroupTableHeading>
                <div
                    v-if="!isLoading && sortedGroups.length === 0"
                    class="col-span-3 py-24 text-center">
                    <UserGroupIcon class="w-8 text-icon-default inline pb-2"></UserGroupIcon>
                    <h3 class="text-text-primary font-semibold">No groups found</h3>
                    <p v-if="canUpdateMembers()" class="pb-5">Create your first group now!</p>
                    <SecondaryButton
                        v-if="canUpdateMembers()"
                        :icon="PlusIcon"
                        @click="showCreateGroupModal = true"
                        >Create your First Group</SecondaryButton
                    >
                </div>
                <template v-for="group in sortedGroups" :key="group.id">
                    <MemberGroupTableRow
                        :group="group"
                        :group-members="group.groupMembers"></MemberGroupTableRow>
                </template>
            </div>
        </div>
    </div>
</template>
