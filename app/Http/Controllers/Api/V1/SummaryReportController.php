<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\TimeEntryAggregationType;
use App\Http\Requests\V1\Report\SummaryReportRequest;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Service\TimeEntryAggregationService;
use App\Service\TimeEntryFilter;
use App\Service\TimeEntryMemberFilterResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class SummaryReportController extends Controller
{
    /**
     * Summary endpoint compatible with Clockify reports/summary payload.
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function summary(Organization $organization, SummaryReportRequest $request, TimeEntryAggregationService $timeEntryAggregationService): array
    {
        $this->checkPermission($organization, 'time-entries:view:all');

        [$group1Type, $group2Type, $group3Type] = $request->getGroups();
        $user = $this->user();
        $showBillableRate = $this->member($organization)->role !== Role::Employee->value || $organization->employees_can_see_billable_rates;
        $start = $request->getStartUtc();
        $end = $request->getEndUtc();

        $timeEntriesAggregateQuery = $this->getSummaryAggregateQuery($organization, $request);
        $timeEntriesCount = (int) $timeEntriesAggregateQuery->clone()->count();

        $aggregatedData = $timeEntryAggregationService->getAggregatedTimeEntriesWithDescriptions(
            $timeEntriesAggregateQuery,
            $group1Type,
            $group2Type,
            $request->getTimezone(),
            $request->getWeekStartFromUserDefault($user->week_start),
            false,
            $start,
            $end,
            $showBillableRate,
            null,
            null,
            $group3Type
        );

        return [
            'totals' => [[
                '_id' => 'TOTAL',
                'duration' => $aggregatedData['seconds'],
                'amount' => $aggregatedData['cost'],
                'count' => $timeEntriesCount,
            ]],
            'groupOne' => $this->transformNodes($aggregatedData['grouped_data'], $group1Type),
            'timeEntriesCount' => $timeEntriesCount,
        ];
    }

    /**
     * @return Builder<TimeEntry>
     */
    private function getSummaryAggregateQuery(Organization $organization, SummaryReportRequest $request): Builder
    {
        $timeEntriesQuery = TimeEntry::query()
            ->whereBelongsTo($organization, 'organization');

        $filter = new TimeEntryFilter($timeEntriesQuery);
        $filter->addEnd($request->getEndUtc());
        $filter->addStart($request->getStartUtc());
        $filter->addMemberIdsFilter(TimeEntryMemberFilterResolver::resolveForOrganization(
            $organization,
            $request->getUserIds(),
            null
        ));
        $filter->addProjectIdsFilter($request->getProjectIds());
        $filter->addTagIdsFilter($request->getTagIds());
        $filter->addTaskIdsFilter($request->getTaskIds());
        $filter->addClientIdsFilter($request->getClientIds());
        $filter->addBillableFilter($request->getBillableAsFilterValue());

        return $filter->get();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function transformNodes(?array $nodes, ?TimeEntryAggregationType $nodeType): array
    {
        if ($nodes === null) {
            return [];
        }

        $result = [];
        foreach ($nodes as $node) {
            /** @var array<int, array<string, mixed>>|null $children */
            $children = $node['grouped_data'];
            $nextType = isset($node['grouped_type']) && is_string($node['grouped_type']) ? TimeEntryAggregationType::from($node['grouped_type']) : null;

            $key = $node['key'] ?? null;
            $description = $node['description'] ?? null;

            $result[] = [
                '_id' => $this->normalizeNodeId($key, $nodeType),
                'name' => $this->normalizeNodeName($key, $description, $nodeType),
                'duration' => $node['seconds'],
                'amount' => $node['cost'],
                'children' => $this->transformNodes($children, $nextType),
            ];
        }

        return $result;
    }

    private function normalizeNodeId(?string $key, ?TimeEntryAggregationType $nodeType): string
    {
        // Billable carries '0'/'1' as the key; expose Clockify-style stable identifiers.
        if ($nodeType === TimeEntryAggregationType::Billable) {
            if ($key === '1') {
                return 'BILLABLE';
            }
            if ($key === '0') {
                return 'NON_BILLABLE';
            }

            return 'NO_BILLABLE';
        }

        if ($key !== null && $key !== '') {
            return $key;
        }

        return match ($nodeType) {
            TimeEntryAggregationType::Task => 'NO_TASK',
            TimeEntryAggregationType::Project => 'NO_PROJECT',
            TimeEntryAggregationType::Client => 'NO_CLIENT',
            TimeEntryAggregationType::Tag => 'NO_TAG',
            TimeEntryAggregationType::User => 'NO_USER',
            TimeEntryAggregationType::Description => 'NO_DESCRIPTION',
            default => 'NO_VALUE',
        };
    }

    private function normalizeNodeName(?string $key, ?string $description, ?TimeEntryAggregationType $nodeType): string
    {
        if ($nodeType === TimeEntryAggregationType::Billable) {
            return match ($key) {
                '1' => 'Billable',
                '0' => 'Non-billable',
                default => 'Without billable status',
            };
        }

        if ($description !== null && $description !== '') {
            return $description;
        }

        // For ID-keyed groupings the bare UUID is not a useful display name.
        if ($key !== null && $key !== '' && $this->isHumanReadableKey($nodeType)) {
            return $key;
        }

        return match ($nodeType) {
            TimeEntryAggregationType::Task => 'No task',
            TimeEntryAggregationType::Project => 'No project',
            TimeEntryAggregationType::Client => 'No client',
            TimeEntryAggregationType::Tag => 'No tag',
            TimeEntryAggregationType::User => 'No user',
            TimeEntryAggregationType::Description => 'No description',
            default => 'No value',
        };
    }

    /**
     * Whether the raw key value is suitable as a fallback display name when no description was loaded.
     * Time-bucket keys (e.g. '2024-01-01') and free-text keys are; UUIDs are not.
     */
    private function isHumanReadableKey(?TimeEntryAggregationType $nodeType): bool
    {
        return match ($nodeType) {
            TimeEntryAggregationType::Day,
            TimeEntryAggregationType::Week,
            TimeEntryAggregationType::Month,
            TimeEntryAggregationType::Year,
            TimeEntryAggregationType::Description => true,
            default => false,
        };
    }
}
