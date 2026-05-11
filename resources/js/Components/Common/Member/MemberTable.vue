<script setup lang="ts">
import MemberTableHeading from '@/Components/Common/Member/MemberTableHeading.vue';
import MemberTableRow from '@/Components/Common/Member/MemberTableRow.vue';
import { useMembersQuery } from '@/utils/useMembersQuery';
import { useMemberGroupsQuery } from '@/utils/useMemberGroupsQuery';
import type { Member } from '@/packages/api/src';
import { computed, ref } from 'vue';
import {
    useVueTable,
    getCoreRowModel,
    getSortedRowModel,
    type SortingState,
} from '@tanstack/vue-table';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';

export type SortColumn = 'name' | 'email' | 'role' | 'billable_rate' | 'status' | 'group';
export type SortDirection = 'asc' | 'desc';

const props = defineProps<{
    sortColumn: SortColumn;
    sortDirection: SortDirection;
}>();

const emit = defineEmits<{
    sort: [column: SortColumn, direction: SortDirection];
}>();

const { members } = useMembersQuery();
const { memberGroups } = useMemberGroupsQuery();

const search = ref('');
const roleFilter = ref<string>('');
const groupFilter = ref<string>('');

const roleOrder: Record<string, number> = {
    owner: 0,
    admin: 1,
    manager: 2,
    employee: 3,
    placeholder: 4,
};

const sorting = computed<SortingState>(() => [
    {
        id: props.sortColumn,
        desc: props.sortDirection === 'desc',
    },
]);

const columns = [
    {
        id: 'name',
        accessorFn: (row: Member) => row.name.toLowerCase(),
    },
    {
        id: 'email',
        accessorFn: (row: Member) => row.email.toLowerCase(),
    },
    {
        id: 'role',
        accessorFn: (row: Member) => roleOrder[row.role] ?? 99,
    },
    {
        id: 'billable_rate',
        sortDescFirst: true,
        sortUndefined: 'last' as const,
        accessorFn: (row: Member) => {
            if (row.billable_rate === null) return undefined;
            return row.billable_rate;
        },
    },
    {
        id: 'status',
        accessorFn: (row: Member) => (row.is_placeholder ? 1 : 0),
    },
    {
        id: 'group',
        accessorFn: (row: Member) => {
            const first = (row.groups ?? [])[0]?.name;
            return first ? first.toLowerCase() : '';
        },
    },
];

const descFirstColumns = new Set<SortColumn>(
    columns.filter((c) => c.sortDescFirst).map((c) => c.id as SortColumn)
);

function handleSort(column: SortColumn) {
    if (props.sortColumn === column) {
        emit('sort', column, props.sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
        emit('sort', column, descFirstColumns.has(column) ? 'desc' : 'asc');
    }
}

const filteredMembers = computed<Member[]>(() => {
    const searchTerm = search.value.trim().toLowerCase();
    return members.value.filter((member) => {
        if (roleFilter.value && member.role !== roleFilter.value) return false;
        if (groupFilter.value) {
            const groupIds = (member.groups ?? []).map((g) => g.id);
            if (!groupIds.includes(groupFilter.value)) return false;
        }
        if (searchTerm) {
            const haystack = `${member.name} ${member.email}`.toLowerCase();
            if (!haystack.includes(searchTerm)) return false;
        }
        return true;
    });
});

const table = useVueTable({
    get data() {
        return filteredMembers.value;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    state: {
        get sorting() {
            return sorting.value;
        },
    },
    manualSorting: false,
});

const sortedMembers = computed(() => {
    return table.getRowModel().rows.map((row) => row.original);
});

const roleOptions = [
    { value: '', label: 'All roles' },
    { value: 'owner', label: 'Owner' },
    { value: 'admin', label: 'Admin' },
    { value: 'manager', label: 'Manager' },
    { value: 'employee', label: 'Employee' },
    { value: 'placeholder', label: 'Placeholder' },
];
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-3 px-4 sm:px-6 lg:px-8 3xl:px-12 py-3 border-b border-default-background-separator">
        <div class="relative">
            <MagnifyingGlassIcon
                class="w-4 h-4 text-icon-default absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <TextInput
                v-model="search"
                type="search"
                placeholder="Search by name or email"
                class="!pl-9 min-w-[260px]"
                data-testid="member_search" />
        </div>
        <select
            v-model="roleFilter"
            class="rounded-md border border-input-border bg-input-background px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-ring"
            data-testid="member_role_filter">
            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <select
            v-model="groupFilter"
            class="rounded-md border border-input-border bg-input-background px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-ring"
            data-testid="member_group_filter">
            <option value="">All groups</option>
            <option v-for="group in memberGroups" :key="group.id" :value="group.id">
                {{ group.name }}
            </option>
        </select>
    </div>
    <div class="flow-root max-w-[100vw] overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div
                data-testid="member_table"
                class="grid min-w-full"
                style="grid-template-columns: 1fr 1fr 160px 180px 150px 1fr 130px">
                <MemberTableHeading
                    :sort-column="props.sortColumn"
                    :sort-direction="props.sortDirection"
                    :desc-first-columns="descFirstColumns"
                    @sort="handleSort"></MemberTableHeading>
                <template v-for="member in sortedMembers" :key="member.id">
                    <MemberTableRow :member="member"></MemberTableRow>
                </template>
            </div>
        </div>
    </div>
</template>
