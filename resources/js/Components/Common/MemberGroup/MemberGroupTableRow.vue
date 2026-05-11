<script setup lang="ts">
import type { Member, MemberGroup } from '@/packages/api/src';
import { useMemberGroupsStore } from '@/utils/useMemberGroups';
import MemberGroupMoreOptionsDropdown from '@/Components/Common/MemberGroup/MemberGroupMoreOptionsDropdown.vue';
import MemberGroupEditModal from '@/Components/Common/MemberGroup/MemberGroupEditModal.vue';
import MemberGroupManageMembersModal from '@/Components/Common/MemberGroup/MemberGroupManageMembersModal.vue';
import TableRow from '@/Components/TableRow.vue';
import { canUpdateMembers } from '@/utils/permissions';
import { ref } from 'vue';
import { PencilSquareIcon, TrashIcon, UsersIcon } from '@heroicons/vue/20/solid';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/packages/ui/src';

const props = defineProps<{
    group: MemberGroup;
    groupMembers: Member[];
}>();

const showEditModal = ref(false);
const showManageMembersModal = ref(false);

async function deleteGroup() {
    await useMemberGroupsStore().deleteMemberGroup(props.group.id);
}
</script>

<template>
    <ContextMenu>
        <ContextMenuTrigger as-child>
            <TableRow>
                <div
                    class="whitespace-nowrap flex items-center space-x-5 3xl:pl-12 py-4 pr-3 text-sm font-medium text-text-primary pl-4 sm:pl-6 lg:pl-8 3xl:pl-12">
                    <span>
                        {{ group.name }}
                    </span>
                </div>
                <div class="px-3 py-4 text-sm text-text-primary">
                    <div v-if="groupMembers.length > 0" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="member in groupMembers"
                            :key="member.id"
                            class="inline-flex items-center rounded bg-secondary px-2 py-0.5 text-xs text-text-secondary border border-border-secondary">
                            {{ member.name }}
                        </span>
                    </div>
                    <span v-else class="text-text-tertiary">No members assigned</span>
                </div>
                <div
                    class="relative whitespace-nowrap flex items-center pl-3 text-right text-sm font-medium sm:pr-0 pr-4 sm:pr-6 lg:pr-8 3xl:pr-12">
                    <MemberGroupMoreOptionsDropdown
                        v-if="canUpdateMembers()"
                        :group="group"
                        @edit="showEditModal = true"
                        @manage-members="showManageMembersModal = true"
                        @delete="deleteGroup"></MemberGroupMoreOptionsDropdown>
                </div>
                <MemberGroupEditModal
                    v-model:show="showEditModal"
                    :group="group"></MemberGroupEditModal>
                <MemberGroupManageMembersModal
                    v-model:show="showManageMembersModal"
                    :group="group"
                    :group-members="groupMembers"></MemberGroupManageMembersModal>
            </TableRow>
        </ContextMenuTrigger>
        <ContextMenuContent class="min-w-[180px]">
            <ContextMenuItem
                v-if="canUpdateMembers()"
                class="space-x-3"
                @select="showEditModal = true">
                <PencilSquareIcon class="w-4 h-4 text-icon-default" />
                <span>Rename</span>
            </ContextMenuItem>
            <ContextMenuItem
                v-if="canUpdateMembers()"
                class="space-x-3"
                @select="showManageMembersModal = true">
                <UsersIcon class="w-4 h-4 text-icon-default" />
                <span>Manage members</span>
            </ContextMenuItem>
            <ContextMenuSeparator v-if="canUpdateMembers()" />
            <ContextMenuItem
                v-if="canUpdateMembers()"
                class="space-x-3 text-destructive"
                @select="deleteGroup()">
                <TrashIcon class="w-4 h-4 text-icon-default" />
                <span>Delete</span>
            </ContextMenuItem>
        </ContextMenuContent>
    </ContextMenu>
</template>

<style scoped></style>
