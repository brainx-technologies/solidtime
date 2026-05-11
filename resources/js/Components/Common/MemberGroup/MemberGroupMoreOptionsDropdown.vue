<script setup lang="ts">
import { TrashIcon, PencilSquareIcon, UsersIcon } from '@heroicons/vue/20/solid';
import { canUpdateMembers } from '@/utils/permissions';
import type { MemberGroup } from '@/packages/api/src';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/packages/ui/src';

const emit = defineEmits<{
    edit: [];
    manageMembers: [];
    delete: [];
}>();
const props = defineProps<{
    group: MemberGroup;
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                class="focus-visible:outline-none focus-visible:bg-card-background rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:opacity-100 hover:bg-card-background group-hover:opacity-100 opacity-20 transition-opacity text-text-secondary"
                :aria-label="'Actions for Group ' + props.group.name">
                <svg
                    class="h-8 w-8 p-1 rounded-full"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        fill="none"
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M12 5.92A.96.96 0 1 0 12 4a.96.96 0 0 0 0 1.92m0 7.04a.96.96 0 1 0 0-1.92a.96.96 0 0 0 0 1.92M12 20a.96.96 0 1 0 0-1.92a.96.96 0 0 0 0 1.92" />
                </svg>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="min-w-[180px]" align="end">
            <DropdownMenuItem
                v-if="canUpdateMembers()"
                :aria-label="'Rename Group ' + props.group.name"
                data-testid="member_group_edit"
                class="flex items-center space-x-3 cursor-pointer"
                @click="emit('edit')">
                <PencilSquareIcon class="w-5 text-icon-active" />
                <span>Rename</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="canUpdateMembers()"
                :aria-label="'Manage members of Group ' + props.group.name"
                data-testid="member_group_manage_members"
                class="flex items-center space-x-3 cursor-pointer"
                @click="emit('manageMembers')">
                <UsersIcon class="w-5 text-icon-active" />
                <span>Manage members</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="canUpdateMembers()"
                :aria-label="'Delete Group ' + props.group.name"
                data-testid="member_group_delete"
                class="flex items-center space-x-3 cursor-pointer text-destructive focus:text-destructive"
                @click="emit('delete')">
                <TrashIcon class="w-5" />
                <span>Delete</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<style scoped></style>
