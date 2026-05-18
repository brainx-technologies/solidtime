import { useMutation, useQueryClient } from '@tanstack/vue-query';
import {
    api,
    type CreateTimeEntryBody,
    type TimeEntry,
    type UpdateMultipleTimeEntriesChangeset,
} from '@/packages/api/src';
import { getCurrentMembershipId, getCurrentOrganizationId } from '@/utils/useUser';
import { useNotificationsStore } from '@/utils/notification';

/** Bulk time-entry APIs return `{ success, error }` id arrays; unwrap `data` when the client passes an axios-style envelope. */
function bulkTimeEntryIdsResult(response: unknown): { success: string[]; error: string[] } {
    const body = response as {
        success?: unknown;
        error?: unknown;
        data?: { success?: unknown; error?: unknown };
    };
    const raw = body.data ?? body;
    const success = Array.isArray(raw.success) ? (raw.success as string[]) : [];
    const error = Array.isArray(raw.error) ? (raw.error as string[]) : [];

    return { success, error };
}

export function useTimeEntriesMutations() {
    const queryClient = useQueryClient();
    const { handleApiRequestNotifications, addNotification } = useNotificationsStore();

    const { mutateAsync: createTimeEntry } = useMutation({
        mutationFn: async (timeEntry: Omit<CreateTimeEntryBody, 'member_id'>) => {
            const organizationId = getCurrentOrganizationId();
            const memberId = getCurrentMembershipId();
            if (organizationId && memberId !== undefined) {
                const newTimeEntry = {
                    ...timeEntry,
                    member_id: memberId,
                } as CreateTimeEntryBody;

                return await handleApiRequestNotifications(
                    () =>
                        api.createTimeEntry(newTimeEntry, {
                            params: {
                                organization: organizationId,
                            },
                        }),
                    'Time entry created successfully',
                    'Failed to create time entry'
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: updateTimeEntry } = useMutation({
        mutationFn: async (timeEntry: TimeEntry) => {
            const organizationId = getCurrentOrganizationId();
            if (organizationId) {
                return await handleApiRequestNotifications(
                    () =>
                        api.updateTimeEntry(timeEntry, {
                            params: {
                                organization: organizationId,
                                timeEntry: timeEntry.id,
                            },
                        }),
                    'Time entry updated successfully',
                    'Failed to update time entry'
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: updateTimeEntries } = useMutation({
        mutationFn: async ({
            ids,
            changes,
        }: {
            ids: string[];
            changes: UpdateMultipleTimeEntriesChangeset;
        }) => {
            const organizationId = getCurrentOrganizationId();
            if (organizationId) {
                return await handleApiRequestNotifications(
                    () =>
                        api.updateMultipleTimeEntries(
                            {
                                ids: ids,
                                changes: changes,
                            },
                            {
                                params: {
                                    organization: organizationId,
                                },
                            }
                        ),
                    undefined,
                    'Failed to update time entries',
                    (response) => {
                        const { success, error } = bulkTimeEntryIdsResult(response);
                        const ok = success.length;
                        const fail = error.length;
                        if (ok > 0 && fail === 0) {
                            addNotification('success', 'Time entries updated successfully');
                        } else if (ok > 0 && fail > 0) {
                            addNotification(
                                'success',
                                `${ok} time ${ok === 1 ? 'entry' : 'entries'} updated; ${fail} could not be updated.`
                            );
                        } else if (fail > 0) {
                            addNotification(
                                'error',
                                'Time entries could not be updated',
                                'None of the selected time entries could be updated. They may be locked or you may not have permission.'
                            );
                        } else {
                            addNotification('error', 'No time entries were updated');
                        }
                    }
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: deleteTimeEntry } = useMutation({
        mutationFn: async (timeEntryId: string) => {
            const organizationId = getCurrentOrganizationId();
            if (organizationId) {
                return await handleApiRequestNotifications(
                    () =>
                        api.deleteTimeEntry(undefined, {
                            params: {
                                organization: organizationId,
                                timeEntry: timeEntryId,
                            },
                        }),
                    'Time entry deleted successfully',
                    'Failed to delete time entry'
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: deleteTimeEntries } = useMutation({
        mutationFn: async (timeEntries: TimeEntry[]) => {
            const organizationId = getCurrentOrganizationId();
            const timeEntryIds = timeEntries.map((entry) => entry.id);
            if (organizationId) {
                return await handleApiRequestNotifications(
                    () =>
                        api.deleteTimeEntries(undefined, {
                            queries: {
                                ids: timeEntryIds,
                            },
                            params: {
                                organization: organizationId,
                            },
                        }),
                    undefined,
                    'Failed to delete time entries',
                    (response) => {
                        const { success, error } = bulkTimeEntryIdsResult(response);
                        const ok = success.length;
                        const fail = error.length;
                        if (ok > 0 && fail === 0) {
                            addNotification('success', 'Time entries deleted successfully');
                        } else if (ok > 0 && fail > 0) {
                            addNotification(
                                'success',
                                `${ok} time ${ok === 1 ? 'entry' : 'entries'} deleted; ${fail} could not be deleted.`
                            );
                        } else if (fail > 0) {
                            addNotification(
                                'error',
                                'Time entries could not be deleted',
                                'None of the selected time entries could be deleted. They may be locked or you may not have permission.'
                            );
                        } else {
                            addNotification('error', 'No time entries were deleted');
                        }
                    }
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    return {
        createTimeEntry,
        updateTimeEntry,
        updateTimeEntries,
        deleteTimeEntry,
        deleteTimeEntries,
    };
}
