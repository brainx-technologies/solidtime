<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\MemberTimeEntryEditOverride;

use App\Http\Resources\V1\BaseResource;
use App\Models\MemberTimeEntryEditOverride;
use Illuminate\Http\Request;

/**
 * @property MemberTimeEntryEditOverride $resource
 */
class MemberTimeEntryEditOverrideResource extends BaseResource
{
    /**
     * @return array<string, string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string $id ID of the override */
            'id' => $this->resource->getKey(),
            /** @var string $member_id ID of the member granted temporary edit access */
            'member_id' => $this->resource->member_id,
            /** @var string|null $member_name Member's display name (when member relation is loaded) */
            'member_name' => $this->resource->member?->user?->name,
            /** @var string $applies_on Calendar date (Y-m-d) in the org edit policy timezone for which the override unlocks locked own entries */
            'applies_on' => $this->resource->applies_on->format('Y-m-d'),
            /** @var string $editable_until ISO 8601 UTC moment until which edits are allowed */
            'editable_until' => $this->formatDateTime($this->resource->editable_until),
        ];
    }
}
