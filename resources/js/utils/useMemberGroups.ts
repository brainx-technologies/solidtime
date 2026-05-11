import { defineStore } from 'pinia';
import type {
    CreateMemberGroupBody,
    MemberGroup,
    SyncMemberGroupMembersBody,
    UpdateMemberGroupBody,
} from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { api } from '@/packages/api/src';
import { useNotificationsStore } from '@/utils/notification';
import { useMutation, useQueryClient } from '@tanstack/vue-query';

export const useMemberGroupsStore = defineStore('member-groups', () => {
    const { handleApiRequestNotifications } = useNotificationsStore();
    const queryClient = useQueryClient();

    function invalidate() {
        queryClient.invalidateQueries({ queryKey: ['member-groups'] });
        queryClient.invalidateQueries({ queryKey: ['members'] });
    }

    async function createMemberGroup(name: string): Promise<MemberGroup | undefined> {
        const organizationId = getCurrentOrganizationId();
        if (!organizationId) {
            throw new Error('Failed to create group because organization ID is missing.');
        }
        const body: CreateMemberGroupBody = { name };
        const response = await handleApiRequestNotifications(
            () =>
                api.createMemberGroup(body, {
                    params: { organization: organizationId },
                }),
            'Group created successfully',
            'Failed to create group'
        );
        if (response?.data) {
            invalidate();
            return response.data;
        }
    }

    async function deleteMemberGroup(memberGroupId: string) {
        const organizationId = getCurrentOrganizationId();
        if (!organizationId) return;
        await handleApiRequestNotifications(
            () =>
                api.deleteMemberGroup(undefined, {
                    params: {
                        organization: organizationId,
                        memberGroup: memberGroupId,
                    },
                }),
            'Group deleted successfully',
            'Failed to delete group'
        );
        invalidate();
    }

    const { mutateAsync: updateMemberGroup } = useMutation({
        mutationFn: async ({
            memberGroupId,
            body,
        }: {
            memberGroupId: string;
            body: UpdateMemberGroupBody;
        }) => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) return;
            return await handleApiRequestNotifications(
                () =>
                    api.updateMemberGroup(body, {
                        params: {
                            organization: organizationId,
                            memberGroup: memberGroupId,
                        },
                    }),
                'Group updated successfully',
                'Failed to update group'
            );
        },
        onSuccess: () => {
            invalidate();
        },
    });

    const { mutateAsync: syncMemberGroupMembers } = useMutation({
        mutationFn: async ({
            memberGroupId,
            body,
        }: {
            memberGroupId: string;
            body: SyncMemberGroupMembersBody;
        }) => {
            const organizationId = getCurrentOrganizationId();
            if (!organizationId) return;
            return await handleApiRequestNotifications(
                () =>
                    api.syncMemberGroupMembers(body, {
                        params: {
                            organization: organizationId,
                            memberGroup: memberGroupId,
                        },
                    }),
                'Group members updated successfully',
                'Failed to update group members'
            );
        },
        onSuccess: () => {
            invalidate();
        },
    });

    return {
        createMemberGroup,
        deleteMemberGroup,
        updateMemberGroup,
        syncMemberGroupMembers,
    };
});
