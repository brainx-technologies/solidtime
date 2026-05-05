<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\MemberGroup;

use App\Http\Resources\V1\BaseResource;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Http\Request;

/**
 * @property MemberGroup $resource
 */
class MemberGroupResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, string|int|null|array<int, array<string, string>>>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string $id ID */
            'id' => $this->resource->id,
            /** @var string $name Name */
            'name' => $this->resource->name,
            /** @var string $organization_id ID of the organization the group belongs to */
            'organization_id' => $this->resource->organization_id,
            /** @var int $members_count Number of members assigned to this group */
            'members_count' => (int) ($this->resource->members_count ?? $this->resource->members()->count()),
            /** @var array<int, array{id: string, user_id: string, name: string}>|null $members Members assigned to this group, only included when explicitly loaded */
            'members' => $this->whenLoaded('members', function () {
                return $this->resource->members->map(function (Member $member): array {
                    return [
                        'id' => $member->getKey(),
                        'user_id' => $member->user_id,
                        'name' => $member->user?->name ?? '',
                    ];
                })->all();
            }),
            /** @var string $created_at When the group was created */
            'created_at' => $this->formatDateTime($this->resource->created_at),
            /** @var string $updated_at When the group was last updated */
            'updated_at' => $this->formatDateTime($this->resource->updated_at),
        ];
    }
}
