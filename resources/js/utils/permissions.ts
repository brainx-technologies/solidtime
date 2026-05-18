import { usePage } from '@inertiajs/vue3';
import { getCurrentMembershipId } from '@/utils/useUser';

const page = usePage<{
    auth: {
        permissions: string[];
    };
}>();

function currentUserHasPermission(permission: string) {
    if (Array.isArray(page.props.auth.permissions)) {
        return page.props.auth.permissions.includes(permission);
    }
    return false;
}

export function canUpdateOrganization() {
    return currentUserHasPermission('organizations:update');
}

export function canViewProjects() {
    return currentUserHasPermission('projects:view');
}

export function canCreateProjects() {
    return currentUserHasPermission('projects:create');
}

export function canUpdateProjects() {
    return currentUserHasPermission('projects:update');
}

export function canDeleteProjects() {
    return currentUserHasPermission('projects:delete');
}

export function canViewProjectMembers() {
    return currentUserHasPermission('project-members:view');
}

export function canCreateTasks() {
    return currentUserHasPermission('tasks:create');
}

export function canUpdateTasks() {
    return currentUserHasPermission('tasks:update');
}

export function canDeleteTasks() {
    return currentUserHasPermission('tasks:delete');
}

export function canCreateClients() {
    return currentUserHasPermission('clients:create');
}

export function canUpdateClients() {
    return currentUserHasPermission('clients:update');
}

export function canDeleteClients() {
    return currentUserHasPermission('clients:delete');
}

export function canViewClients() {
    return currentUserHasPermission('clients:view');
}

export function canViewMembers() {
    return currentUserHasPermission('members:view');
}

export function canUpdateMembers() {
    return currentUserHasPermission('members:update');
}

export function canViewMemberTimeEntryOverrides() {
    return currentUserHasPermission('member:time-entry-override:view');
}

export function canCreateMemberTimeEntryOverrideAll() {
    return currentUserHasPermission('member:time-entry-override:create:all');
}

export function canCreateMemberTimeEntryOverrideAllExceptOwn() {
    return currentUserHasPermission('member:time-entry-override:create:all_except_own');
}

export function canUpdateMemberTimeEntryOverrideAll() {
    return currentUserHasPermission('member:time-entry-override:update:all');
}

export function canUpdateMemberTimeEntryOverrideAllExceptOwn() {
    return currentUserHasPermission('member:time-entry-override:update:all_except_own');
}

export function canUpdateMemberTimeEntryOverrides() {
    return (
        canUpdateMemberTimeEntryOverrideAll() || canUpdateMemberTimeEntryOverrideAllExceptOwn()
    );
}

export function canDeleteMemberTimeEntryOverrideAll() {
    return currentUserHasPermission('member:time-entry-override:delete:all');
}

export function canDeleteMemberTimeEntryOverrideAllExceptOwn() {
    return currentUserHasPermission('member:time-entry-override:delete:all_except_own');
}

export function canDeleteMemberTimeEntryOverrides() {
    return (
        canDeleteMemberTimeEntryOverrideAll() || canDeleteMemberTimeEntryOverrideAllExceptOwn()
    );
}

/** Delete row only if allowed for overrides whose beneficiary is `targetMemberId`. */
export function canDeleteMemberTimeEntryOverrideForMember(targetMemberId: string) {
    if (canDeleteMemberTimeEntryOverrideAll()) {
        return true;
    }
    if (!canDeleteMemberTimeEntryOverrideAllExceptOwn()) {
        return false;
    }
    const own = getCurrentMembershipId();
    return own !== null && own !== targetMemberId;
}

/** Any permission that should surface the Members → Edit overrides tab or related UI. */
export function canAccessMemberTimeEntryOverridesTab() {
    return (
        canViewMemberTimeEntryOverrides() ||
        canCreateMemberTimeEntryOverrideAll() ||
        canCreateMemberTimeEntryOverrideAllExceptOwn() ||
        canUpdateMemberTimeEntryOverrides() ||
        canDeleteMemberTimeEntryOverrides()
    );
}

export function canDeleteMembers() {
    return currentUserHasPermission('members:delete');
}

export function canMergeMembers() {
    return currentUserHasPermission('members:merge-into');
}

export function canMakeMembersPlaceholders() {
    return currentUserHasPermission('members:make-placeholder');
}

export function canInvitePlaceholderMembers() {
    return currentUserHasPermission('members:invite-placeholder');
}

export function canCreateInvitations() {
    return currentUserHasPermission('invitations:create');
}

export function canViewTags() {
    return currentUserHasPermission('tags:view');
}

export function canCreateTags() {
    return currentUserHasPermission('tags:create');
}

export function canUpdateTags() {
    return currentUserHasPermission('tags:update');
}

export function canDeleteTags() {
    return currentUserHasPermission('tags:delete');
}

export function canManageBilling() {
    return currentUserHasPermission('billing');
}

export function canViewReport() {
    return currentUserHasPermission('reports:view');
}
export function canUpdateReport() {
    return currentUserHasPermission('reports:update');
}
export function canDeleteReport() {
    return currentUserHasPermission('reports:delete');
}

export function canViewAllTimeEntries() {
    return currentUserHasPermission('time-entries:view:all');
}
export function canViewInvoices() {
    return currentUserHasPermission('invoices:view');
}
export function canCreateReports() {
    return currentUserHasPermission('reports:create');
}
