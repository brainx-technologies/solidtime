<script setup lang="ts">
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import DialogModal from '@/packages/ui/src/DialogModal.vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import Checkbox from '@/packages/ui/src/Input/Checkbox.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import { computed, ref, watch } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { useMembersQuery } from '@/utils/useMembersQuery';
import { useMemberGroupsStore } from '@/utils/useMemberGroups';
import type { Member, MemberGroup } from '@/packages/api/src';

const show = defineModel('show', { default: false });
const saving = ref(false);
const search = ref('');

const props = defineProps<{
    group: MemberGroup;
    groupMembers: Member[];
}>();

const { members } = useMembersQuery();
const selectedIds = ref<Set<string>>(new Set());

watch(
    () => [show.value, props.group.id, props.groupMembers],
    () => {
        if (show.value) {
            selectedIds.value = new Set(props.groupMembers.map((member) => member.id));
            search.value = '';
        }
    },
    { immediate: true, deep: true }
);

const filteredMembers = computed<Member[]>(() => {
    const term = search.value.trim().toLowerCase();
    const list = members.value.filter((member) => !member.is_placeholder);
    if (!term) return list;
    return list.filter((member) => {
        const haystack = `${member.name} ${member.email}`.toLowerCase();
        return haystack.includes(term);
    });
});

function toggleMember(id: string) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
}

async function submit() {
    saving.value = true;
    try {
        await useMemberGroupsStore().syncMemberGroupMembers({
            memberGroupId: props.group.id,
            body: { member_ids: Array.from(selectedIds.value) },
        });
        show.value = false;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <DialogModal closeable :show="show" @close="show = false">
        <template #title>
            <div class="flex space-x-2">
                <span>Manage members of {{ group.name }}</span>
            </div>
        </template>

        <template #content>
            <div class="space-y-4">
                <div class="relative">
                    <MagnifyingGlassIcon
                        class="w-4 h-4 text-icon-default absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <TextInput
                        v-model="search"
                        type="search"
                        placeholder="Search by name or email"
                        class="!pl-9 w-full" />
                </div>
                <div
                    class="max-h-80 overflow-y-auto border border-card-background-separator rounded">
                    <div
                        v-if="filteredMembers.length === 0"
                        class="px-3 py-6 text-center text-sm text-text-tertiary">
                        No members match your search.
                    </div>
                    <button
                        v-for="member in filteredMembers"
                        :key="member.id"
                        type="button"
                        class="flex items-center w-full text-left gap-3 px-3 py-2 text-sm text-text-primary hover:bg-card-background-active border-b border-card-background-separator last:border-b-0"
                        @click="toggleMember(member.id)">
                        <Checkbox
                            :checked="selectedIds.has(member.id)"
                            aria-hidden="true"
                            :tabindex="-1"
                            class="pointer-events-none" />
                        <div class="flex flex-col min-w-0">
                            <span class="truncate">{{ member.name }}</span>
                            <span class="truncate text-xs text-text-tertiary">{{
                                member.email
                            }}</span>
                        </div>
                    </button>
                </div>
                <div class="text-sm text-text-secondary">
                    {{ selectedIds.size }} member{{ selectedIds.size === 1 ? '' : 's' }} selected
                </div>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="show = false">Cancel</SecondaryButton>
            <PrimaryButton
                class="ms-3"
                :class="{ 'opacity-25': saving }"
                :disabled="saving"
                @click="submit">
                Save
            </PrimaryButton>
        </template>
    </DialogModal>
</template>

<style scoped></style>
