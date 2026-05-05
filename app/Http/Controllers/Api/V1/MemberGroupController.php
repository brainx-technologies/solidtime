<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\MemberGroup\MemberGroupIndexRequest;
use App\Http\Requests\V1\MemberGroup\MemberGroupStoreRequest;
use App\Http\Requests\V1\MemberGroup\MemberGroupSyncMembersRequest;
use App\Http\Requests\V1\MemberGroup\MemberGroupUpdateRequest;
use App\Http\Resources\V1\MemberGroup\MemberGroupCollection;
use App\Http\Resources\V1\MemberGroup\MemberGroupResource;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class MemberGroupController extends Controller
{
    protected function checkPermission(Organization $organization, string $permission, ?MemberGroup $memberGroup = null): void
    {
        parent::checkPermission($organization, $permission);
        if ($memberGroup !== null && $memberGroup->organization_id !== $organization->getKey()) {
            throw new AuthorizationException('Member group does not belong to organization');
        }
    }

    /**
     * Get member groups
     *
     * @return MemberGroupCollection<MemberGroupResource>
     *
     * @throws AuthorizationException
     *
     * @operationId getMemberGroups
     */
    public function index(Organization $organization, MemberGroupIndexRequest $request): MemberGroupCollection
    {
        $this->checkPermission($organization, 'members:view');

        $memberGroups = MemberGroup::query()
            ->whereBelongsTo($organization, 'organization')
            ->withCount('members')
            ->orderBy('name', 'asc')
            ->paginate(config('app.pagination_per_page_default'));

        return new MemberGroupCollection($memberGroups);
    }

    /**
     * Create member group
     *
     * @throws AuthorizationException
     *
     * @operationId createMemberGroup
     */
    public function store(Organization $organization, MemberGroupStoreRequest $request): MemberGroupResource
    {
        $this->checkPermission($organization, 'members:update');

        $memberGroup = new MemberGroup;
        $memberGroup->name = (string) $request->input('name');
        $memberGroup->organization()->associate($organization);
        $memberGroup->save();

        $memberGroup->loadCount('members');

        return new MemberGroupResource($memberGroup);
    }

    /**
     * Update member group
     *
     * @throws AuthorizationException
     *
     * @operationId updateMemberGroup
     */
    public function update(Organization $organization, MemberGroup $memberGroup, MemberGroupUpdateRequest $request): MemberGroupResource
    {
        $this->checkPermission($organization, 'members:update', $memberGroup);

        $memberGroup->name = (string) $request->input('name');
        $memberGroup->save();

        $memberGroup->loadCount('members');

        return new MemberGroupResource($memberGroup);
    }

    /**
     * Delete member group
     *
     * @throws AuthorizationException
     *
     * @operationId deleteMemberGroup
     */
    public function destroy(Organization $organization, MemberGroup $memberGroup): JsonResponse
    {
        $this->checkPermission($organization, 'members:update', $memberGroup);

        $memberGroup->delete();

        return response()->json(null, 204);
    }

    /**
     * Replace the members of the group with the provided list of member IDs.
     *
     * All current memberships of the group will be removed and replaced by the provided ones.
     * Members not present in `member_ids` will be detached.
     *
     * @throws AuthorizationException
     *
     * @operationId syncMemberGroupMembers
     */
    public function syncMembers(Organization $organization, MemberGroup $memberGroup, MemberGroupSyncMembersRequest $request): MemberGroupResource
    {
        $this->checkPermission($organization, 'members:update', $memberGroup);

        $memberIds = $request->getMemberIds();

        if (count($memberIds) > 0) {
            $validIds = Member::query()
                ->whereBelongsTo($organization, 'organization')
                ->whereIn('id', $memberIds)
                ->pluck('id')
                ->all();
        } else {
            $validIds = [];
        }

        $memberGroup->members()->sync($validIds);
        $memberGroup->load('members.user');
        $memberGroup->loadCount('members');

        return new MemberGroupResource($memberGroup);
    }
}
