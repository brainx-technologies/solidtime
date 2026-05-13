<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\Api\TimeEntryEditWindowExpiredApiException;
use App\Models\Member;
use App\Models\MemberTimeEntryEditOverride;
use App\Models\Organization;
use App\Models\OrganizationTimeEntryEditPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Centralizes the rules for "members without org-wide time-entry permissions
 * may not change time entries that fall inside an organization-defined past lock window".
 *
 * Lock formula (when policy is enabled):
 *   lockMomentForStart = start.copy()
 *       .timezone(policy.timezone)
 *       .startOfDay()
 *       .addDays(policy.lock_after_days)
 *       .setTime(policy.cutoff_hour, policy.cutoff_minute)
 *
 * Locked when: now(policy.timezone) >= lockMomentForStart, no active override for that entry's
 * local policy date, and the member does not have any of time-entries:create:all,
 * time-entries:update:all, time-entries:delete:all.
 *
 * An override row grants unlock only for time entries whose `start` falls on {@see MemberTimeEntryEditOverride::$applies_on}
 * in the policy timezone, while `editable_until` is still in the future.
 */
class TimeEntryEditLockService
{
    public function __construct(
        private readonly PermissionStore $permissionStore,
    ) {}

    /**
     * Tiny in-memory cache so we don't re-query the policy/override
     * multiple times in a single request (e.g. bulk update).
     *
     * @var array<string, OrganizationTimeEntryEditPolicy|null>
     */
    private array $policyCache = [];

    /**
     * @var array<string, list<string>> organizationId:memberId → list of Y-m-d dates with an active override
     */
    private array $activeOverrideDatesCache = [];

    public function getPolicy(Organization $organization): ?OrganizationTimeEntryEditPolicy
    {
        $key = $organization->getKey();

        if (! array_key_exists($key, $this->policyCache)) {
            $this->policyCache[$key] = OrganizationTimeEntryEditPolicy::query()
                ->whereBelongsTo($organization, 'organization')
                ->first();
        }

        return $this->policyCache[$key];
    }

    public function isPolicyActive(Organization $organization): bool
    {
        $policy = $this->getPolicy($organization);

        return $policy !== null && $policy->enabled;
    }

    /**
     * True when the member has an override that is still active (`editable_until` in the future)
     * and whose `applies_on` equals the calendar date of `$start` in the policy timezone.
     */
    public function hasActiveOverrideForEntryStart(
        Organization $organization,
        Member $member,
        CarbonInterface $start,
        OrganizationTimeEntryEditPolicy $policy,
    ): bool {
        $entryDate = Carbon::parse($start)->timezone($policy->timezone)->format('Y-m-d');

        return in_array($entryDate, $this->getActiveOverrideDatesForMember($organization, $member), true);
    }

    /**
     * @return list<string> Distinct Y-m-d dates with at least one non-expired override
     */
    private function getActiveOverrideDatesForMember(Organization $organization, Member $member): array
    {
        $key = $organization->getKey().':'.$member->getKey();

        if (! array_key_exists($key, $this->activeOverrideDatesCache)) {
            $this->activeOverrideDatesCache[$key] = MemberTimeEntryEditOverride::query()
                ->whereBelongsTo($organization, 'organization')
                ->whereBelongsTo($member, 'member')
                ->where('editable_until', '>', now())
                ->pluck('applies_on')
                ->map(static function ($date): string {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->unique()
                ->values()
                ->all();
        }

        return $this->activeOverrideDatesCache[$key];
    }

    /**
     * True when this member is subject to the past-entry edit lock for this entry start (no
     * matching active override and none of time-entries:create:all, time-entries:update:all,
     * time-entries:delete:all).
     */
    private function isMemberSubjectToEditLock(
        Organization $organization,
        Member $member,
        CarbonInterface $start,
        OrganizationTimeEntryEditPolicy $policy,
    ): bool {
        if ($this->hasActiveOverrideForEntryStart($organization, $member, $start, $policy)) {
            return false;
        }

        $user = $member->relationLoaded('user') ? $member->user : $member->user()->first();
        if ($user === null) {
            return false;
        }

        if ($this->permissionStore->userHas($organization, $user, 'time-entries:create:all')) {
            return false;
        }
        if ($this->permissionStore->userHas($organization, $user, 'time-entries:update:all')) {
            return false;
        }
        if ($this->permissionStore->userHas($organization, $user, 'time-entries:delete:all')) {
            return false;
        }

        return true;
    }

    /**
     * Given a time entry start, returns the moment after which an
     * employee can no longer edit/delete entries from that day.
     */
    public function getLockMomentForStart(OrganizationTimeEntryEditPolicy $policy, CarbonInterface $start): Carbon
    {
        [$cutoffHours, $cutoffMinutes] = $this->parseCutoffTime($policy->cutoff_time);

        return Carbon::parse($start)
            ->timezone($policy->timezone)
            ->startOfDay()
            ->addDays($policy->lock_after_days)
            ->setTime($cutoffHours, $cutoffMinutes);
    }

    /**
     * Midnight at the beginning of the current calendar day in the policy timezone, as UTC.
     * Used as the **exclusive** end of the local-day window for auto-stop candidates (`start` &lt; this).
     */
    public function startOfCurrentPolicyLocalDayUtc(OrganizationTimeEntryEditPolicy $policy): Carbon
    {
        return Carbon::now($policy->timezone)->copy()->startOfDay()->utc();
    }

    /**
     * Inclusive start of the local-day window for auto-stop candidates: midnight of
     * `today - lock_after_days` in the policy timezone, as UTC.
     * With `lock_after_days = 1` this is yesterday’s date only (with {@see startOfCurrentPolicyLocalDayUtc} as the end).
     */
    public function autoStopCandidateRangeStartUtc(OrganizationTimeEntryEditPolicy $policy): Carbon
    {
        return Carbon::now($policy->timezone)
            ->copy()
            ->subDays($policy->lock_after_days)
            ->startOfDay()
            ->utc();
    }

    /**
     * True when the current moment in the policy timezone is on or after today’s wall-clock cutoff
     * (so the cron can skip entirely before that time each local day).
     */
    public function isPastTodayCutoffInPolicyTimezone(OrganizationTimeEntryEditPolicy $policy): bool
    {
        [$cutoffHours, $cutoffMinutes] = $this->parseCutoffTime($policy->cutoff_time);
        $todayCutoff = Carbon::now($policy->timezone)->copy()->startOfDay()->setTime($cutoffHours, $cutoffMinutes);

        return Carbon::now($policy->timezone)->greaterThanOrEqualTo($todayCutoff);
    }

    /**
     * Returns true only when the given start date falls in the locked
     * window for the given member — i.e. policy is enabled, now is past the
     * org lock moment for that start, and the member is subject to the lock
     * (no org-wide time-entry :all permissions, no date-scoped override for this start).
     */
    public function isStartLocked(Organization $organization, Member $member, CarbonInterface $start): bool
    {
        $policy = $this->getPolicy($organization);
        if ($policy === null || ! $policy->enabled) {
            return false;
        }

        $lockMoment = $this->getLockMomentForStart($policy, $start);

        if (now($policy->timezone)->lessThan($lockMoment)) {
            return false;
        }

        return $this->isMemberSubjectToEditLock($organization, $member, $start, $policy);
    }

    /**
     * UTC instant to set as `end` when auto-stopping a forgotten timer after the edit lock
     * has taken effect: the last second before the lock boundary, or the lock instant itself
     * if subtracting a second would not be after `start`.
     */
    public function autoStopEndUtcBeforeLockMoment(OrganizationTimeEntryEditPolicy $policy, CarbonInterface $start): Carbon
    {
        $lockUtc = $this->getLockMomentForStart($policy, $start)->utc();
        $candidate = $lockUtc->copy()->subSecond();
        $startUtc = Carbon::parse($start)->utc();

        return $candidate->greaterThan($startUtc) ? $candidate : $lockUtc;
    }

    /**
     * Enforces the org past-entry edit lock for rows that belong to the authenticated member's user.
     * No-op when policy is off, when the member has an active override for this entry's policy-local date
     * or any time-entries:*:all permission,
     * or when $entryOwnerUserId is not the member's user (e.g. admin editing someone else's entry).
     *
     * @throws TimeEntryEditWindowExpiredApiException
     */
    public function assertTimeEntryEditLock(Organization $organization, Member $member, string $entryOwnerUserId, CarbonInterface $start): void
    {
        if ($entryOwnerUserId !== $member->user_id) {
            return;
        }

        if ($this->isStartLocked($organization, $member, $start)) {
            throw new TimeEntryEditWindowExpiredApiException;
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseCutoffTime(string $cutoffTime): array
    {
        $parts = explode(':', $cutoffTime);
        $hours = isset($parts[0]) ? (int) $parts[0] : 0;
        $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

        return [$hours, $minutes];
    }
}
