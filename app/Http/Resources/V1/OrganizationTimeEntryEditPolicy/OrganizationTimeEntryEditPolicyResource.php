<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\OrganizationTimeEntryEditPolicy;

use App\Http\Resources\V1\BaseResource;
use App\Models\OrganizationTimeEntryEditPolicy;
use Illuminate\Http\Request;

/**
 * @property OrganizationTimeEntryEditPolicy|null $resource
 */
class OrganizationTimeEntryEditPolicyResource extends BaseResource
{
    /**
     * @return array<string, string|bool|int|null>
     */
    public function toArray(Request $request): array
    {
        $policy = $this->resource;

        return [
            /** @var string|null $id Policy ID (null when no policy is configured yet) */
            'id' => $policy?->getKey(),
            /** @var bool $enabled Whether the past-edit lock is enabled */
            'enabled' => $policy?->enabled ?? false,
            /** @var int $lock_after_days Number of days after the entry's date before the cutoff applies */
            'lock_after_days' => $policy?->lock_after_days ?? 1,
            /** @var string $cutoff_time Daily cutoff time in HH:MM (24h) */
            'cutoff_time' => $policy?->cutoff_time !== null ? mb_substr($policy->cutoff_time, 0, 5) : '09:00',
            /** @var string $timezone IANA timezone name used to evaluate the cutoff */
            'timezone' => $policy?->timezone ?? (string) config('app.timezone', 'UTC'),
        ];
    }
}
