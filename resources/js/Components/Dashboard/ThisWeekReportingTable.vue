<script setup lang="ts">
import ReportingRow from '@/Components/Common/Reporting/ReportingRow.vue';
import ReportingGroupBySelect from '@/Components/Common/Reporting/ReportingGroupBySelect.vue';
import { XMarkIcon } from '@heroicons/vue/20/solid';
import {
    formatReportingDuration,
    getDayJsInstance,
    getLocalizedDayJs,
} from '@/packages/ui/src/utils/time';
import { formatCents } from '@/packages/ui/src/utils/money';
import { getOrganizationCurrencyString } from '@/utils/money';
import { type GroupingOption, useReportingStore } from '@/utils/useReporting';
import { getCurrentMembershipId, getCurrentOrganizationId, getCurrentRole } from '@/utils/useUser';
import {
    api,
    type AggregatedTimeEntries,
    type AggregatedTimeEntriesQueryParams,
    type Organization,
} from '@/packages/api/src';
import { useQuery } from '@tanstack/vue-query';
import { useStorage } from '@vueuse/core';
import { computed, inject, type ComputedRef, watch } from 'vue';
import { mapGroupingTreeToTableRows, type GroupingTreeNode } from '@/utils/reportingGroupedTable';

const organization = inject<ComputedRef<Organization>>('organization');

const group = useStorage<GroupingOption>('dashboard-reporting-group', 'project');
const subGroup = useStorage<GroupingOption>('dashboard-reporting-sub-group', 'task');
const thirdGroup = useStorage<GroupingOption | null>('dashboard-reporting-third-group', null);

const reportingStore = useReportingStore();
const { groupByOptions, getNameForReportingRowEntry } = reportingStore;

watch(
    group,
    () => {
        if (group.value === subGroup.value) {
            const fallbackOption = groupByOptions.find((el) => el.value !== group.value);
            if (fallbackOption?.value) {
                subGroup.value = fallbackOption.value;
            }
        }
    },
    { immediate: true }
);

watch(
    [group, subGroup],
    () => {
        if (thirdGroup.value === null) {
            return;
        }
        if (thirdGroup.value === group.value || thirdGroup.value === subGroup.value) {
            const fallbackOption = groupByOptions.find(
                (el) => el.value !== group.value && el.value !== subGroup.value
            );
            thirdGroup.value = fallbackOption?.value ?? null;
        }
    },
    { immediate: true }
);

const organizationId = computed(() => getCurrentOrganizationId());

const weekStartUtc = computed(() => {
    return getLocalizedDayJs(getDayJsInstance()().format())
        .startOf('week')
        .startOf('day')
        .utc()
        .format();
});

const weekEndUtc = computed(() => {
    return getLocalizedDayJs(getDayJsInstance()().format()).endOf('day').utc().format();
});

const queryParams = computed<AggregatedTimeEntriesQueryParams>(() => {
    return {
        start: weekStartUtc.value,
        end: weekEndUtc.value,
        group: group.value,
        sub_group: subGroup.value,
        third_group: thirdGroup.value ?? undefined,
        member_id: getCurrentRole() === 'employee' ? getCurrentMembershipId() : undefined,
    };
});

const { data: reportingResponse, isLoading } = useQuery({
    queryKey: [
        'dashboardThisWeekReporting',
        organizationId,
        weekStartUtc,
        weekEndUtc,
        group,
        subGroup,
        thirdGroup,
    ],
    queryFn: () => {
        return api.getAggregatedTimeEntries({
            params: {
                organization: organizationId.value!,
            },
            queries: queryParams.value,
        });
    },
    enabled: computed(() => !!organizationId.value),
});

const aggregatedTableTimeEntries = computed<AggregatedTimeEntries | null>(() => {
    return (reportingResponse.value?.data as AggregatedTimeEntries | undefined) ?? null;
});

const tableData = computed(() => {
    const rootType = aggregatedTableTimeEntries.value?.grouped_type ?? null;
    return (
        aggregatedTableTimeEntries.value?.grouped_data?.map((entry) =>
            mapGroupingTreeToTableRows(entry as GroupingTreeNode, rootType, (e, gt) =>
                getNameForReportingRowEntry(e.key ?? null, gt)
            )
        ) ?? []
    );
});

const showBillableRate = computed(() => {
    return !!(
        getCurrentRole() !== 'employee' || organization?.value?.employees_can_see_billable_rates
    );
});
</script>

<template>
    <div class="rounded-lg bg-card-background border border-card-border">
        <div
            class="text-sm flex text-text-primary pt-3 items-center space-x-3 font-medium px-6 border-b border-card-background-separator pb-3">
            <span>Group by</span>
            <ReportingGroupBySelect
                v-model="group"
                :group-by-options="groupByOptions"></ReportingGroupBySelect>
            <span>and</span>
            <ReportingGroupBySelect
                v-model="subGroup"
                :group-by-options="
                    groupByOptions.filter((el) => el.value !== group)
                "></ReportingGroupBySelect>
            <template v-if="thirdGroup !== null">
                <span>and</span>
                <ReportingGroupBySelect
                    v-model="thirdGroup"
                    :group-by-options="
                        groupByOptions.filter(
                            (el) => el.value !== group && el.value !== subGroup
                        )
                    "></ReportingGroupBySelect>
                <button
                    class="inline-flex items-center text-text-tertiary hover:text-text-primary"
                    title="Remove third group"
                    @click="thirdGroup = null">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </template>
            <button
                v-else
                class="text-sm text-text-tertiary hover:text-text-primary"
                @click="
                    thirdGroup =
                        groupByOptions.find(
                            (el) => el.value !== group && el.value !== subGroup
                        )?.value ?? null
                ">
                + Add group
            </button>
        </div>

        <div
            class="grid items-center"
            :style="`grid-template-columns: 1fr 100px ${showBillableRate ? '150px' : ''}`">
            <div
                class="contents [&>*]:border-card-background-separator [&>*]:border-b [&>*]:pb-1.5 [&>*]:pt-1 text-text-tertiary text-sm">
                <div class="pl-6">Name</div>
                <div class="text-right" :class="!showBillableRate ? 'pr-6' : ''">Duration</div>
                <div v-if="showBillableRate" class="text-right pr-6">Cost</div>
            </div>

            <div
                v-if="isLoading"
                class="flex justify-center py-10 text-text-tertiary"
                :class="showBillableRate ? 'col-span-3' : 'col-span-2'">
                Loading reporting data…
            </div>

            <template
                v-else-if="
                    aggregatedTableTimeEntries?.grouped_data &&
                    aggregatedTableTimeEntries.grouped_data?.length > 0
                ">
                <ReportingRow
                    v-for="entry in tableData"
                    :key="entry.description ?? 'none'"
                    :currency="getOrganizationCurrencyString()"
                    :show-cost="showBillableRate"
                    :entry="entry"></ReportingRow>
                <div class="contents [&>*]:transition text-text-tertiary [&>*]:h-[50px]">
                    <div class="flex items-center pl-6 font-medium">
                        <span>Total</span>
                    </div>
                    <div
                        class="justify-end flex items-center font-medium"
                        :class="!showBillableRate ? 'pr-6' : ''">
                        {{
                            formatReportingDuration(
                                aggregatedTableTimeEntries.seconds,
                                organization?.interval_format,
                                organization?.number_format
                            )
                        }}
                    </div>
                    <div
                        v-if="showBillableRate"
                        class="justify-end pr-6 flex items-center font-medium">
                        {{
                            aggregatedTableTimeEntries.cost
                                ? formatCents(
                                      aggregatedTableTimeEntries.cost,
                                      getOrganizationCurrencyString(),
                                      organization?.currency_format,
                                      organization?.currency_symbol,
                                      organization?.number_format
                                  )
                                : '--'
                        }}
                    </div>
                </div>
            </template>

            <div
                v-else
                class="chart flex flex-col items-center justify-center py-12"
                :class="showBillableRate ? 'col-span-3' : 'col-span-2'">
                <p class="text-lg text-text-primary font-medium">No time entries found</p>
                <p>Try to track some time entries this week</p>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
