<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\Organization\OrganizationTimeEntryEditPolicyStoreRequest;
use App\Http\Resources\V1\OrganizationTimeEntryEditPolicy\OrganizationTimeEntryEditPolicyResource;
use App\Models\Organization;
use App\Models\OrganizationTimeEntryEditPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class OrganizationTimeEntryEditPolicyController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function show(Organization $organization): OrganizationTimeEntryEditPolicyResource
    {
        $this->checkPermission($organization, 'organizations:update');

        $policy = OrganizationTimeEntryEditPolicy::query()
            ->whereBelongsTo($organization, 'organization')
            ->first();

        return new OrganizationTimeEntryEditPolicyResource($policy);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(Organization $organization, OrganizationTimeEntryEditPolicyStoreRequest $request): OrganizationTimeEntryEditPolicyResource
    {
        $this->checkPermission($organization, 'organizations:update');

        $policy = OrganizationTimeEntryEditPolicy::query()->updateOrCreate(
            ['organization_id' => $organization->getKey()],
            [
                'enabled' => $request->boolean('enabled'),
                'lock_after_days' => (int) $request->input('lock_after_days'),
                'cutoff_time' => (string) $request->input('cutoff_time').':00',
                'timezone' => (string) $request->input('timezone'),
            ]
        );

        return new OrganizationTimeEntryEditPolicyResource($policy);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'organizations:update');

        OrganizationTimeEntryEditPolicy::query()
            ->whereBelongsTo($organization, 'organization')
            ->delete();

        return response()->json(null, 204);
    }
}
