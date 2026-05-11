<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

final class TimeEntryMemberFilterResolver
{
    /**
     * Union of explicit member IDs and members belonging to the given groups.
     * Returns null when neither members nor groups are selected (no member filter).
     * Returns an empty list when a group filter is applied but yields no members and no explicit IDs.
     *
     * @param  array<string>|null  $memberIds
     * @param  array<string>|null  $memberGroupIds
     * @return array<string>|null
     */
    public static function resolveForOrganization(
        Organization $organization,
        ?array $memberIds,
        ?array $memberGroupIds,
    ): ?array {
        $explicit = collect($memberIds ?? [])
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '');

        $groupIds = collect($memberGroupIds ?? [])
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values();

        $fromGroups = collect();
        if ($groupIds->isNotEmpty()) {
            $fromGroups = Member::query()
                ->whereBelongsTo($organization, 'organization')
                ->whereHas('groups', function (Builder $q) use ($groupIds): void {
                    $q->whereIn('member_groups.id', $groupIds->all());
                })
                ->pluck('id');
        }

        $merged = $explicit->merge($fromGroups)->unique()->values();

        if ($explicit->isEmpty() && $groupIds->isEmpty()) {
            return null;
        }

        /** @var array<string> */
        return $merged->all();
    }
}
