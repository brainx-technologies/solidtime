import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import type { MemberGroup } from '@/packages/api/src';
import { computed } from 'vue';
import { fetchAllPages } from '@/utils/fetchAllPages';

export async function fetchAllMemberGroups(organizationId: string): Promise<MemberGroup[]> {
    return fetchAllPages((page) =>
        api.getMemberGroups({
            params: { organization: organizationId },
            queries: { page },
        })
    );
}

export function useMemberGroupsQuery() {
    const queryClient = useQueryClient();

    const query = useQuery({
        queryKey: computed(() => ['member-groups', getCurrentOrganizationId()]),
        queryFn: async () => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) throw new Error('No organization');
            const data = await fetchAllMemberGroups(organizationId);
            return { data };
        },
        enabled: () => !!getCurrentOrganizationId(),
        staleTime: 1000 * 30,
    });

    const memberGroups = computed<MemberGroup[]>(() => query.data.value?.data ?? []);

    const invalidateMemberGroups = () => {
        queryClient.invalidateQueries({ queryKey: ['member-groups'] });
    };

    return {
        ...query,
        memberGroups,
        invalidateMemberGroups,
    };
}
