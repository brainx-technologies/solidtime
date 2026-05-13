<script setup lang="ts">
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import { formatDate, formatDateTimeLocalized } from '@/packages/ui/src/utils/time';
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';
import { useNotificationsStore } from '@/utils/notification';
import { useOrganizationStore } from '@/utils/useOrganization';
import { getCurrentOrganizationId } from '@/utils/useUser';
import axios from 'axios';
import TableHeading from '@/Components/Common/TableHeading.vue';
import TableRow from '@/Components/TableRow.vue';

const orgStore = useOrganizationStore();
const { organization } = storeToRefs(orgStore);

const OVERRIDE_PAGE_TITLE = 'Time entry edit overrides';
const OVERRIDE_PAGE_DESCRIPTION =
    'When your organization uses a past-entry edit lock, use Add override in the header to grant a member temporary access to edit their own entries for one calendar day (in the lock policy timezone) until a chosen end time (stored in UTC; shown in your timezone).';

const { handleApiRequestNotifications } = useNotificationsStore();
const organizationId = getCurrentOrganizationId();

type MemberOverride = {
    id: string;
    member_id: string;
    member_name: string;
    applies_on: string;
    editable_until: string;
};

const overrides = ref<MemberOverride[]>([]);

async function loadOverrides() {
    if (!organizationId) {
        return;
    }
    try {
        await handleApiRequestNotifications(
            () =>
                axios.get<{ data: MemberOverride[] }>(
                    `/api/v1/organizations/${organizationId}/member-time-entry-edit-overrides`
                ),
            undefined,
            'Failed to load time entry edit overrides',
            (response) => {
                overrides.value = response.data.data;
            }
        );
    } catch {
        // Error notification already shown
    }
}

onMounted(async () => {
    if (!organization.value) {
        await orgStore.fetchOrganization();
    }
    await loadOverrides();
});

function formatAppliesOnDisplay(isoDate: string): string {
    return formatDate(isoDate, organization.value?.date_format);
}

function formatOverrideDisplay(isoUtc: string): string {
    return formatDateTimeLocalized(
        isoUtc,
        organization.value?.date_format,
        organization.value?.time_format
    );
}

async function removeOverride(id: string) {
    if (!organizationId) {
        return;
    }
    try {
        await handleApiRequestNotifications(
            () =>
                axios.delete(
                    `/api/v1/organizations/${organizationId}/member-time-entry-edit-overrides/${id}`
                ),
            'Member override removed',
            'Failed to remove member override'
        );
        await loadOverrides();
    } catch {
        // Error notification already shown
    }
}

defineExpose({
    refresh: loadOverrides,
});
</script>

<template>
    <div>
        <header class="px-4 sm:px-6 lg:px-8 3xl:px-12 py-6 border-b border-default-background-separator">
            <h2 class="text-lg font-medium text-text-primary">{{ OVERRIDE_PAGE_TITLE }}</h2>
            <p class="mt-2 max-w-3xl text-sm text-text-secondary">
                {{ OVERRIDE_PAGE_DESCRIPTION }}
            </p>
        </header>

        <div class="flow-root max-w-[100vw] overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <div
                    class="grid min-w-full"
                    data-testid="member_time_entry_override_table"
                    style="grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, 1.5fr) 7rem">
                    <TableHeading>
                        <div
                            class="px-3 py-1.5 text-left text-text-tertiary pl-4 sm:pl-6 lg:pl-8 3xl:pl-12">
                            Member
                        </div>
                        <div class="px-3 py-1.5 text-left text-text-tertiary">Unlock day</div>
                        <div class="px-3 py-1.5 text-left text-text-tertiary">Editable until</div>
                        <div
                            class="relative py-1.5 pl-3 pr-4 sm:pr-6 lg:pr-8 3xl:pr-12 text-right text-text-tertiary bg-row-heading-background">
                            <span class="sr-only">Actions</span>
                        </div>
                    </TableHeading>

                    <template v-if="overrides.length === 0">
                        <div
                            class="col-span-4 py-12 text-center text-sm text-text-secondary bg-row-background">
                            No active overrides. Use Add override when a member needs temporary access
                            after the edit lock.
                        </div>
                    </template>

                    <template v-for="override in overrides" :key="override.id">
                        <TableRow>
                            <div
                                class="min-w-0 px-3 py-4 text-sm text-text-primary pl-4 sm:pl-6 lg:pl-8 3xl:pl-12">
                                <span class="font-medium truncate block">{{ override.member_name }}</span>
                            </div>
                            <div
                                class="min-w-0 px-3 py-4 text-sm text-text-secondary whitespace-nowrap">
                                {{ formatAppliesOnDisplay(override.applies_on) }}
                            </div>
                            <div
                                class="min-w-0 px-3 py-4 text-sm text-text-secondary whitespace-nowrap">
                                {{ formatOverrideDisplay(override.editable_until) }}
                            </div>
                            <div
                                class="relative flex items-center justify-end px-3 py-4 text-right text-sm font-medium pr-4 sm:pr-6 lg:pr-8 3xl:pr-12">
                                <SecondaryButton
                                    size="small"
                                    data-testid="member_override_remove"
                                    @click="removeOverride(override.id)">
                                    Remove
                                </SecondaryButton>
                            </div>
                        </TableRow>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
