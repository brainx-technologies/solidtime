<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Member;

use App\Http\Resources\V1\BaseResource;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Http\Request;

/**
 * @property Member $resource
 */
class MemberResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, string|bool|int|null|array<int, array<string, string>>>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string $id ID of membership */
            'id' => $this->resource->id,
            /** @var string $id ID of user */
            'user_id' => $this->resource->user->id,
            /** @var string $name Name */
            'name' => $this->resource->user->name,
            /** @var string $email Email */
            'email' => $this->resource->user->email,
            /** @var string $role Role */
            'role' => $this->resource->role,
            /** @var bool $is_placeholder Placeholder user for imports, user might not really exist and does not know about this placeholder membership */
            'is_placeholder' => $this->resource->user->is_placeholder,
            /** @var int|null $billable_rate Billable rate in cents per hour */
            'billable_rate' => $this->resource->billable_rate,
            /** @var array<int, array{id: string, name: string}>|null $groups Groups the member is assigned to (only included when the relation is loaded) */
            'groups' => $this->whenLoaded('groups', function () {
                return $this->resource->groups->map(function (MemberGroup $group): array {
                    return [
                        'id' => $group->getKey(),
                        'name' => $group->name,
                    ];
                })->all();
            }),
        ];
    }
}
