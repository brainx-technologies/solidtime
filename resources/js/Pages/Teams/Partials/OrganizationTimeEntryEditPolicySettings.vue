<script setup lang="ts">
import FormSection from '@/Components/FormSection.vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import { computed, onMounted, ref, watch } from 'vue';
import { Field, FieldLabel } from '@/packages/ui/src/field';
import { Checkbox } from '@/packages/ui/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import axios from 'axios';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import TimePickerSimple from '@/packages/ui/src/Input/TimePickerSimple.vue';
import { getDayJsInstance, getLocalizedDayJs } from '@/packages/ui/src/utils/time';
import { useNotificationsStore } from '@/utils/notification';

dayjs.extend(utc);
dayjs.extend(timezone);

const { handleApiRequestNotifications } = useNotificationsStore();
const organizationId = getCurrentOrganizationId();

type TimeEntryEditPolicy = {
    id: string | null;
    enabled: boolean;
    lock_after_days: number;
    cutoff_time: string;
    timezone: string;
};

const policy = ref<TimeEntryEditPolicy>({
    id: null,
    enabled: false,
    lock_after_days: 1,
    cutoff_time: '09:00',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
});

/** Localized instant for TimePickerSimple; kept in sync with policy cutoff + timezone. */
const cutoffPickerDisplay = ref<string | null>(null);
const savingPolicy = ref(false);

const hasOrganization = computed(() => !!organizationId);

function normalizeCutoffHhMm(raw: string): string {
    const trimmed = raw.trim();
    const [h = '0', m = '0'] = trimmed.split(':');
    const hh = h.padStart(2, '0').slice(-2);
    const mm = m.replace(/\D/g, '').padStart(2, '0').slice(0, 2) || '00';
    return `${hh}:${mm}`;
}

function syncCutoffPickerFromPolicy(): void {
    try {
        const pad = normalizeCutoffHhMm(policy.value.cutoff_time);
        if (!policy.value.timezone) {
            return;
        }
        const utcIso = dayjs.tz(`2000-01-01 ${pad}`, policy.value.timezone).utc().format();
        cutoffPickerDisplay.value = getLocalizedDayJs(utcIso).format();
    } catch {
        // Invalid timezone while typing — keep previous picker value
    }
}

watch([() => policy.value.timezone, () => policy.value.cutoff_time], syncCutoffPickerFromPolicy);

onMounted(() => {
    void loadPolicy();
});

async function loadPolicy(): Promise<void> {
    if (!organizationId) {
        return;
    }
    try {
        await handleApiRequestNotifications(
            () =>
                axios.get<{ data: TimeEntryEditPolicy }>(
                    `/api/v1/organizations/${organizationId}/time-entry-edit-policy`
                ),
            undefined,
            'Failed to load time-entry edit policy',
            (response) => {
                policy.value = response.data.data;
                syncCutoffPickerFromPolicy();
            }
        );
    } catch {
        // Error notification already shown by handleApiRequestNotifications
    }
}

async function savePolicy(): Promise<void> {
    if (!organizationId) {
        return;
    }
    savingPolicy.value = true;
    try {
        let cutoffTime = policy.value.cutoff_time;
        if (cutoffPickerDisplay.value) {
            cutoffTime = getDayJsInstance()(cutoffPickerDisplay.value)
                .tz(policy.value.timezone)
                .format('HH:mm');
        }
        await handleApiRequestNotifications(
            () =>
                axios.post(`/api/v1/organizations/${organizationId}/time-entry-edit-policy`, {
                    enabled: policy.value.enabled,
                    lock_after_days: policy.value.lock_after_days,
                    cutoff_time: cutoffTime,
                    timezone: policy.value.timezone,
                }),
            'Time-entry edit policy saved',
            'Failed to save time-entry edit policy'
        );
        await loadPolicy();
    } catch {
        // Error notification already shown by handleApiRequestNotifications
    } finally {
        savingPolicy.value = false;
    }
}
</script>

<template>
    <FormSection @submitted="savePolicy">
        <template #title>Past entry edit lock</template>
        <template #description>
            Restrict how long members can edit or delete their own past time entries. Admins with
            full time-entry permissions are not affected. Use Members → Edit overrides for temporary
            access.
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4 space-y-4">
                <Field orientation="horizontal">
                    <Checkbox
                        id="enableTimeEntryPastEditLock"
                        v-model:checked="policy.enabled"
                        :disabled="!hasOrganization" />
                    <FieldLabel for="enableTimeEntryPastEditLock"
                        >Enable past entry edit lock policy</FieldLabel
                    >
                </Field>
                <Field>
                    <FieldLabel for="lockAfterDays">Lock after days</FieldLabel>
                    <input
                        id="lockAfterDays"
                        v-model.number="policy.lock_after_days"
                        type="number"
                        min="1"
                        class="w-full rounded-md border border-input bg-background px-3 py-2" />
                </Field>
                <Field>
                    <FieldLabel for="cutoffTime">Cutoff time</FieldLabel>
                    <TimePickerSimple
                        id="cutoffTime"
                        v-model="cutoffPickerDisplay"
                        class="w-full max-w-xs" />
                </Field>
                <Field>
                    <FieldLabel for="policyTimezone">Timezone</FieldLabel>
                    <input
                        id="policyTimezone"
                        v-model="policy.timezone"
                        type="text"
                        placeholder="e.g. Asia/Karachi"
                        class="w-full rounded-md border border-input bg-background px-3 py-2" />
                </Field>
                <p class="text-sm text-text-secondary col-span-6 sm:col-span-4">
                    To grant temporary edit access to a specific member, use
                    <strong>Members</strong> → <strong>Edit overrides</strong>.
                </p>
            </div>
        </template>

        <template #actions>
            <PrimaryButton type="submit" :disabled="savingPolicy || !hasOrganization">
                Save edit lock policy
            </PrimaryButton>
        </template>
    </FormSection>
</template>
