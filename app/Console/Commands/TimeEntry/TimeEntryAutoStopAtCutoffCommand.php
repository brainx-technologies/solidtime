<?php

declare(strict_types=1);

namespace App\Console\Commands\TimeEntry;

use App\Models\OrganizationTimeEntryEditPolicy;
use App\Models\TimeEntry;
use App\Service\TimeEntryEditLockService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * For each organization with an enabled lock policy, find any running
 * employee timers that are already in the same edit-lock state as a past
 * entry would be, and stop them with `end` just before the lock instant.
 *
 * This handles the case where an employee forgot to stop the timer the
 * previous day; without this, they could be blocked from editing the
 * past entry but still left with a runaway active timer.
 *
 * For each enabled policy, the command **does nothing until** the policy timezone clock has
 * reached **today’s cutoff** ({@see TimeEntryEditLockService::isPastTodayCutoffInPolicyTimezone}).
 * It then loads running rows whose `start` falls in the local calendar window from
 * {@see TimeEntryEditLockService::autoStopCandidateRangeStartUtc} (inclusive) to
 * {@see TimeEntryEditLockService::startOfCurrentPolicyLocalDayUtc} (exclusive) — for
 * `lock_after_days = 1` that is **yesterday only**. Each row is stopped only when
 * {@see TimeEntryEditLockService::isStartLocked} is true; `end` is set via
 * {@see TimeEntryEditLockService::autoStopEndUtcBeforeLockMoment}.
 */
class TimeEntryAutoStopAtCutoffCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'time-entry:auto-stop-at-cutoff'.
        ' { --dry-run : Do not save changes, just output what would happen }';

    /**
     * @var string
     */
    protected $description = 'Auto-stops running employee time entries that have crossed the org edit-lock cutoff.';

    public function handle(TimeEntryEditLockService $lockService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stopped = 0;
        $policies = OrganizationTimeEntryEditPolicy::query()
            ->where('enabled', true)
            ->get();

        foreach ($policies as $policy) {
            if (! $lockService->isPastTodayCutoffInPolicyTimezone($policy)) {
                continue;
            }

            $rangeStartUtc = $lockService->autoStopCandidateRangeStartUtc($policy);
            $rangeEndUtc = $lockService->startOfCurrentPolicyLocalDayUtc($policy);

            TimeEntry::query()
                ->where('organization_id', $policy->organization_id)
                ->whereNull('end')
                ->where('start', '>=', $rangeStartUtc)
                ->where('start', '<', $rangeEndUtc)
                ->with(['member.user', 'organization'])
                ->chunkById(500, function (Collection $entries) use ($policy, $lockService, $dryRun, &$stopped): void {
                    /** @var Collection<int, TimeEntry> $entries */
                    foreach ($entries as $entry) {
                        $member = $entry->member;
                        $organization = $entry->organization;
                        if ($member === null || $organization === null) {
                            continue;
                        }
                        if (! $lockService->isStartLocked($organization, $member, $entry->start)) {
                            continue;
                        }

                        $stopAt = $lockService->autoStopEndUtcBeforeLockMoment($policy, $entry->start);

                        $this->info(sprintf(
                            'Auto-stopping time entry %s (member %s) at %s',
                            $entry->getKey(),
                            $member->getKey(),
                            $stopAt->toIso8601ZuluString()
                        ));

                        if (! $dryRun) {
                            $entry->end = $stopAt;
                            $entry->save();
                        }
                        $stopped++;
                    }
                });
        }

        $this->comment('Auto-stopped '.$stopped.' time entries.');

        return self::SUCCESS;
    }
}
