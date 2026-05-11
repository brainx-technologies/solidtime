<script setup lang="ts">
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import DialogModal from '@/packages/ui/src/DialogModal.vue';
import { ref, watch } from 'vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import { useFocus } from '@vueuse/core';
import { useMemberGroupsStore } from '@/utils/useMemberGroups';

const show = defineModel('show', { default: false });
const saving = ref(false);
const name = ref('');

const groupNameInput = ref<HTMLInputElement | null>(null);
useFocus(groupNameInput, { initialValue: true });

watch(show, (value) => {
    if (value) {
        name.value = '';
    }
});

async function submit() {
    if (!name.value.trim()) return;
    saving.value = true;
    try {
        const created = await useMemberGroupsStore().createMemberGroup(name.value.trim());
        if (created) {
            show.value = false;
            name.value = '';
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <DialogModal closeable :show="show" @close="show = false">
        <template #title>
            <div class="flex space-x-2">
                <span>Create Group</span>
            </div>
        </template>

        <template #content>
            <div class="flex items-center space-x-4">
                <div class="col-span-6 sm:col-span-4 flex-1">
                    <TextInput
                        id="memberGroupName"
                        ref="groupNameInput"
                        v-model="name"
                        type="text"
                        placeholder="Group Name"
                        class="mt-1 block w-full"
                        required
                        autocomplete="off"
                        @keydown.enter="submit()" />
                </div>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="show = false">Cancel</SecondaryButton>
            <PrimaryButton
                class="ms-3"
                :class="{ 'opacity-25': saving }"
                :disabled="saving || !name.trim()"
                @click="submit">
                Create Group
            </PrimaryButton>
        </template>
    </DialogModal>
</template>

<style scoped></style>
