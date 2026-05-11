<script setup lang="ts">
import { formatReportingDuration } from '@/packages/ui/src/utils/time';
import { formatCents } from '@/packages/ui/src/utils/money';
import GroupedItemsCountButton from '@/packages/ui/src/GroupedItemsCountButton.vue';
import { computed, inject, ref, type ComputedRef } from 'vue';
import type { Organization } from '@/packages/api/src';

type AggregatedGroupedData = GroupedData & {
    grouped_data?: GroupedData[] | null;
};

type GroupedData = {
    seconds: number;
    cost: number | null;
    description: string | null | undefined;
};

const props = withDefaults(
    defineProps<{
        entry: AggregatedGroupedData;
        /** Nesting depth (Clockify-style hierarchy indent); 0 = top level */
        depth?: number;
        currency: string;
        showCost?: boolean;
        /** When > 0, show % of report total duration (Clockify summary style) */
        reportTotalSeconds?: number;
    }>(),
    {
        depth: 0,
        showCost: false,
        reportTotalSeconds: 0,
    }
);

const expanded = ref(false);

const organization = inject<ComputedRef<Organization>>('organization');

const showPercentColumn = computed(() => props.reportTotalSeconds > 0);

const percentOfReportTotal = computed(() => {
    if (!showPercentColumn.value) {
        return null;
    }
    return (props.entry.seconds / props.reportTotalSeconds) * 100;
});

const nameCellStyle = computed(() => ({
    paddingLeft: `${0.75 + props.depth * 1}rem`,
}));

const nestedGridTemplate = computed(() => {
    const parts = ['1fr', '100px'];
    if (showPercentColumn.value) {
        parts.push('minmax(3.25rem,auto)');
    }
    if (props.showCost) {
        parts.push('150px');
    }
    return parts.join(' ');
});
</script>

<template>
    <div
        class="contents text-text-primary [&>*]:transition [&>*]:border-card-background-separator [&>*]:border-b [&>*]:h-[50px]">
        <div class="flex items-center space-x-3" :style="nameCellStyle">
            <GroupedItemsCountButton
                v-if="entry.grouped_data && entry.grouped_data?.length > 0"
                :expanded="expanded"
                @click="expanded = !expanded">
                {{ entry.grouped_data?.length }}
            </GroupedItemsCountButton>
            <span>
                {{ entry.description }}
            </span>
        </div>
        <div class="justify-end flex items-center" :class="!showCost && !showPercentColumn ? 'pr-6' : ''">
            {{
                formatReportingDuration(
                    entry.seconds,
                    organization?.interval_format,
                    organization?.number_format
                )
            }}
        </div>
        <div
            v-if="showPercentColumn"
            class="justify-end flex items-center text-text-secondary text-sm tabular-nums"
            :class="!showCost ? 'pr-6' : ''">
            {{ percentOfReportTotal !== null ? `${percentOfReportTotal.toFixed(2)}%` : '—' }}
        </div>
        <div v-if="showCost" class="justify-end pr-6 flex items-center">
            {{
                entry.cost
                    ? formatCents(
                          entry.cost,
                          props.currency,
                          organization?.currency_format,
                          organization?.currency_symbol,
                          organization?.number_format
                      )
                    : '--'
            }}
        </div>
    </div>
    <div
        v-if="expanded && entry.grouped_data"
        class="col-span-full grid bg-tertiary"
        :style="`grid-template-columns: ${nestedGridTemplate}`">
        <ReportingRow
            v-for="subEntry in entry.grouped_data"
            :key="subEntry.description ?? 'none'"
            :currency="props.currency"
            :show-cost="showCost"
            :report-total-seconds="reportTotalSeconds"
            :depth="depth + 1"
            :entry="subEntry"></ReportingRow>
    </div>
</template>

<style scoped></style>
