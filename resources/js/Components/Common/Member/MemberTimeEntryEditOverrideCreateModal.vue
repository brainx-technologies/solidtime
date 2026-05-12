<script setup lang="ts">
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import DialogModal from '@/packages/ui/src/DialogModal.vue';
import { ref, watch } from 'vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import { Field, FieldLabel } from '@/packages/ui/src/field';
import DatePicker from '@/packages/ui/src/Input/DatePicker.vue';
import TimePickerSimple from '@/packages/ui/src/Input/TimePickerSimple.vue';
import { getLocalizedDayJs } from '@/packages/ui/src/utils/time';
import { useNotificationsStore } from '@/utils/notification';
import { getCurrentOrganizationId } from '@/utils/useUser';
import axios from 'axios';
import MemberCombobox from '@/Components/Common/Member/MemberCombobox.vue';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';

dayjs.extend(utc);

const show = defineModel('show', { default: false });

const emit = defineEmits<{
    created: [];
}>();

const { addNotification, handleApiRequestNotifications } = useNotificationsStore();
const organizationId = getCurrentOrganizationId();

const selectedMemberId = ref('');
const savingOverride = ref(false);

function defaultEditableUntilLocal(): string {
    return getLocalizedDayJs(dayjs.utc().add(1, 'day').toISOString()).format();
}

const localEditableUntil = ref(defaultEditableUntilLocal());

watch(show, (value) => {
    if (value) {
        selectedMemberId.value = '';
        localEditableUntil.value = defaultEditableUntilLocal();
    }
});

async function submit() {
    if (!organizationId || !selectedMemberId.value) {
        return;
    }
    const utcMoment = getLocalizedDayJs(localEditableUntil.value).utc();
    if (!utcMoment.isValid()) {
        addNotification('error', 'Please choose a valid date and time');
        return;
    }
    if (!utcMoment.isAfter(dayjs.utc())) {
        addNotification('error', 'Choose a date and time in the future');
        return;
    }
    savingOverride.value = true;
    try {
        const editableUntilForApi = `${utcMoment.format('YYYY-MM-DDTHH:mm:ss')}Z`;
        await handleApiRequestNotifications(
            () =>
                axios.post(
                    `/api/v1/organizations/${organizationId}/member-time-entry-edit-overrides`,
                    {
                        member_id: selectedMemberId.value,
                        editable_until: editableUntilForApi,
                    }
                ),
            'Member override saved',
            'Failed to save member override'
        );
        show.value = false;
        emit('created');
    } catch {
        // Error notification already shown
    } finally {
        savingOverride.value = false;
    }
}
</script>

<template>
    <DialogModal closeable :show="show" max-width="lg" @close="show = false">
        <template #title>Add time entry edit override</template>

        <template #content>
            <p class="mb-4 text-text-secondary">
                Grant this member temporary permission to edit their own locked past entries until
                the date and time you choose (stored in UTC; shown in your timezone).
            </p>
            <div class="space-y-4 max-w-md">
                <Field>
                    <FieldLabel>Member</FieldLabel>
                    <MemberCombobox v-model="selectedMemberId" />
                </Field>
                <Field>
                    <FieldLabel>Editable until</FieldLabel>
                    <div class="flex flex-col gap-2">
                        <TimePickerSimple
                            v-model="localEditableUntil"
                            class="w-full"
                            data-testid="member_override_editable_until_time"></TimePickerSimple>
                        <DatePicker
                            v-model="localEditableUntil"
                            class="w-full"
                            size="sm"
                            tabindex="1"
                            data-testid="member_override_editable_until_date"></DatePicker>
                    </div>
                </Field>
            </div>
        </template>

        <template #footer>
            <SecondaryButton @click="show = false">Cancel</SecondaryButton>
            <PrimaryButton
                class="ms-3"
                :class="{ 'opacity-25': savingOverride }"
                :disabled="savingOverride || !selectedMemberId"
                data-testid="member_override_modal_save"
                @click="submit">
                Save override
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
