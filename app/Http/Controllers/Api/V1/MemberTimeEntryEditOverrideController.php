<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\Organization\MemberTimeEntryEditOverrideStoreRequest;
use App\Http\Requests\V1\Organization\MemberTimeEntryEditOverrideUpdateRequest;
use App\Http\Resources\V1\MemberTimeEntryEditOverride\MemberTimeEntryEditOverrideResource;
use App\Models\MemberTimeEntryEditOverride;
use App\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class MemberTimeEntryEditOverrideController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->checkPermission($organization, 'members:update');

        $overrides = MemberTimeEntryEditOverride::query()
            ->whereBelongsTo($organization, 'organization')
            ->with('member.user')
            ->orderBy('editable_until', 'desc')
            ->get();

        return MemberTimeEntryEditOverrideResource::collection($overrides);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(Organization $organization, MemberTimeEntryEditOverrideStoreRequest $request): MemberTimeEntryEditOverrideResource
    {
        $this->checkPermission($organization, 'members:update');

        $override = MemberTimeEntryEditOverride::query()->updateOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'member_id' => (string) $request->input('member_id'),
            ],
            [
                'editable_until' => Carbon::parse((string) $request->input('editable_until')),
            ]
        );
        $override->load('member.user');

        return new MemberTimeEntryEditOverrideResource($override);
    }

    /**
     * @throws AuthorizationException
     */
    public function update(Organization $organization, MemberTimeEntryEditOverride $memberTimeEntryEditOverride, MemberTimeEntryEditOverrideUpdateRequest $request): MemberTimeEntryEditOverrideResource
    {
        $this->checkPermission($organization, 'members:update');
        $this->assertBelongsToOrganization($organization, $memberTimeEntryEditOverride);

        $memberTimeEntryEditOverride->editable_until = Carbon::parse((string) $request->input('editable_until'));
        $memberTimeEntryEditOverride->save();
        $memberTimeEntryEditOverride->load('member.user');

        return new MemberTimeEntryEditOverrideResource($memberTimeEntryEditOverride);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Organization $organization, MemberTimeEntryEditOverride $memberTimeEntryEditOverride): JsonResponse
    {
        $this->checkPermission($organization, 'members:update');
        $this->assertBelongsToOrganization($organization, $memberTimeEntryEditOverride);

        $memberTimeEntryEditOverride->delete();

        return response()->json(null, 204);
    }

    /**
     * @throws AuthorizationException
     */
    private function assertBelongsToOrganization(Organization $organization, MemberTimeEntryEditOverride $override): void
    {
        if ($override->organization_id !== $organization->getKey()) {
            throw new AuthorizationException('Override does not belong to organization');
        }
    }
}
