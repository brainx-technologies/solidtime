<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\Organization\MemberTimeEntryEditOverrideStoreRequest;
use App\Http\Requests\V1\Organization\MemberTimeEntryEditOverrideUpdateRequest;
use App\Http\Resources\V1\MemberTimeEntryEditOverride\MemberTimeEntryEditOverrideResource;
use App\Models\Member;
use App\Models\MemberTimeEntryEditOverride;
use App\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class MemberTimeEntryEditOverrideController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->checkPermission($organization, 'member:time-entry-override:view');

        $overrides = MemberTimeEntryEditOverride::query()
            ->whereBelongsTo($organization, 'organization')
            ->with(['member.user', 'grantedByUser'])
            ->orderByDesc('applies_on')
            ->orderByDesc('editable_until')
            ->get();

        return MemberTimeEntryEditOverrideResource::collection($overrides);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(Organization $organization, MemberTimeEntryEditOverrideStoreRequest $request): MemberTimeEntryEditOverrideResource
    {
        $memberId = (string) $request->input('member_id');
        $appliesOn = Carbon::parse((string) $request->input('applies_on'))->startOfDay();
        $editableUntil = Carbon::parse((string) $request->input('editable_until'));

        $existing = MemberTimeEntryEditOverride::query()
            ->where('organization_id', $organization->getKey())
            ->where('member_id', $memberId)
            ->whereDate('applies_on', $appliesOn)
            ->first();

        if ($existing !== null) {
            $this->assertCanUpdateMemberTimeEntryEditOverride($organization, $existing);
            $existing->editable_until = $editableUntil;
            $existing->save();
            $existing->load(['member.user', 'grantedByUser']);

            return new MemberTimeEntryEditOverrideResource($existing);
        }

        $this->assertCanCreateMemberTimeEntryEditOverride($organization, $memberId);

        $override = MemberTimeEntryEditOverride::query()->create([
            'organization_id' => $organization->getKey(),
            'member_id' => $memberId,
            'applies_on' => $appliesOn,
            'editable_until' => $editableUntil,
            'granted_by_user_id' => Auth::id(),
        ]);
        $override->load(['member.user', 'grantedByUser']);

        return new MemberTimeEntryEditOverrideResource($override);
    }

    /**
     * @throws AuthorizationException
     */
    public function update(Organization $organization, MemberTimeEntryEditOverride $memberTimeEntryEditOverride, MemberTimeEntryEditOverrideUpdateRequest $request): MemberTimeEntryEditOverrideResource
    {
        $this->assertCanUpdateMemberTimeEntryEditOverride($organization, $memberTimeEntryEditOverride);
        $this->assertBelongsToOrganization($organization, $memberTimeEntryEditOverride);

        $memberTimeEntryEditOverride->editable_until = Carbon::parse((string) $request->input('editable_until'));
        $memberTimeEntryEditOverride->save();
        $memberTimeEntryEditOverride->load(['member.user', 'grantedByUser']);

        return new MemberTimeEntryEditOverrideResource($memberTimeEntryEditOverride);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Organization $organization, MemberTimeEntryEditOverride $memberTimeEntryEditOverride): JsonResponse
    {
        $this->assertCanDeleteMemberTimeEntryEditOverride($organization, $memberTimeEntryEditOverride);
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

    private function currentMemberIdInOrganization(Organization $organization): ?string
    {
        return Member::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', Auth::id())
            ->value('id');
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanCreateMemberTimeEntryEditOverride(Organization $organization, string $targetMemberId): void
    {
        if ($this->hasPermission($organization, 'member:time-entry-override:create:all')) {
            return;
        }

        if (! $this->hasPermission($organization, 'member:time-entry-override:create:all_except_own')) {
            throw new AuthorizationException;
        }

        $currentMemberId = $this->currentMemberIdInOrganization($organization);

        if ($currentMemberId !== null && $currentMemberId === $targetMemberId) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanUpdateMemberTimeEntryEditOverride(Organization $organization, MemberTimeEntryEditOverride $override): void
    {
        if ($this->hasPermission($organization, 'member:time-entry-override:update:all')) {
            return;
        }

        if (! $this->hasPermission($organization, 'member:time-entry-override:update:all_except_own')) {
            throw new AuthorizationException;
        }

        $currentMemberId = $this->currentMemberIdInOrganization($organization);

        if ($currentMemberId !== null && $currentMemberId === $override->member_id) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanDeleteMemberTimeEntryEditOverride(Organization $organization, MemberTimeEntryEditOverride $override): void
    {
        if ($this->hasPermission($organization, 'member:time-entry-override:delete:all')) {
            return;
        }

        if (! $this->hasPermission($organization, 'member:time-entry-override:delete:all_except_own')) {
            throw new AuthorizationException;
        }

        $currentMemberId = $this->currentMemberIdInOrganization($organization);

        if ($currentMemberId !== null && $currentMemberId === $override->member_id) {
            throw new AuthorizationException;
        }
    }
}
