/**
 * Recursive grouped reporting rows for <ReportingRow />.
 * Kept in one util so pages stay thin and upstream merges touch fewer Vue files.
 */
export type GroupingTreeNode = {
    seconds: number;
    cost: number | null;
    grouped_type: string | null;
    grouped_data?: GroupingTreeNode[] | null;
    key?: string | null;
    description?: string | null;
};

export type ReportingTableGroupedRow = {
    seconds: number;
    cost: number | null;
    description: string | null | undefined;
    grouped_data: ReportingTableGroupedRow[];
};

export function mapGroupingTreeToTableRows(
    entry: GroupingTreeNode,
    groupedType: string | null,
    describe: (entry: GroupingTreeNode, groupedType: string | null) => string | null | undefined
): ReportingTableGroupedRow {
    return {
        seconds: entry.seconds,
        cost: entry.cost,
        description: describe(entry, groupedType),
        grouped_data:
            entry.grouped_data?.map((el) =>
                mapGroupingTreeToTableRows(el, entry.grouped_type, describe)
            ) ?? [],
    };
}
