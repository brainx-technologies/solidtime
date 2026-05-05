<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Report;

use App\Enums\TimeEntryAggregationType;
use App\Enums\Weekday;
use App\Http\Requests\V1\BaseFormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SummaryReportRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'dateRangeStart' => [
                'required',
                'string',
                'date',
            ],
            'dateRangeEnd' => [
                'required',
                'string',
                'date',
                'after:dateRangeStart',
            ],
            'billable' => [
                'nullable',
                'boolean',
            ],
            'timeZone' => [
                'nullable',
                'string',
                'timezone',
            ],
            'weekStart' => [
                'nullable',
                Rule::in(['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']),
            ],
            'summaryFilter' => [
                'nullable',
                'array',
            ],
            'summaryFilter.groups' => [
                'nullable',
                'array',
            ],
            'summaryFilter.groups.*' => [
                'string',
                Rule::in(['DAY', 'WEEK', 'MONTH', 'YEAR', 'USER', 'PROJECT', 'TASK', 'CLIENT', 'BILLABLE', 'DESCRIPTION', 'TAG']),
            ],
            'users' => [
                'nullable',
                'array',
            ],
            'users.ids' => [
                'nullable',
                'array',
            ],
            'users.ids.*' => [
                'string',
            ],
            'projects' => [
                'nullable',
                'array',
            ],
            'projects.ids' => [
                'nullable',
                'array',
            ],
            'projects.ids.*' => [
                'string',
            ],
            'clients' => [
                'nullable',
                'array',
            ],
            'clients.ids' => [
                'nullable',
                'array',
            ],
            'clients.ids.*' => [
                'string',
            ],
            'tags' => [
                'nullable',
                'array',
            ],
            'tags.ids' => [
                'nullable',
                'array',
            ],
            'tags.ids.*' => [
                'string',
            ],
            'tasks' => [
                'nullable',
                'array',
            ],
            'tasks.ids' => [
                'nullable',
                'array',
            ],
            'tasks.ids.*' => [
                'string',
            ],
        ];
    }

    public function getStartUtc(): Carbon
    {
        return Carbon::parse((string) $this->validated('dateRangeStart'), $this->getTimezone())
            ->utc();
    }

    public function getEndUtc(): Carbon
    {
        return Carbon::parse((string) $this->validated('dateRangeEnd'), $this->getTimezone())
            ->utc();
    }

    public function getTimezone(): string
    {
        return (string) ($this->input('timeZone') ?? $this->user()->timezone ?? 'UTC');
    }

    public function getBillableAsFilterValue(): ?string
    {
        if (! $this->has('billable')) {
            return null;
        }

        return $this->boolean('billable') ? 'true' : 'false';
    }

    /**
     * @return array{0: ?TimeEntryAggregationType, 1: ?TimeEntryAggregationType, 2: ?TimeEntryAggregationType}
     */
    public function getGroups(): array
    {
        /** @var array<int, string> $groups */
        $groups = $this->input('summaryFilter.groups', []);
        $mapped = array_values(array_map(
            fn (string $group): TimeEntryAggregationType => $this->mapGroup($group),
            $groups
        ));

        return [
            $mapped[0] ?? null,
            $mapped[1] ?? null,
            $mapped[2] ?? null,
        ];
    }

    /**
     * @return array<string>|null
     */
    public function getUserIds(): ?array
    {
        /** @var array<string>|null $ids */
        $ids = $this->input('users.ids');

        return $ids;
    }

    /**
     * @return array<string>|null
     */
    public function getProjectIds(): ?array
    {
        /** @var array<string>|null $ids */
        $ids = $this->input('projects.ids');

        return $ids;
    }

    /**
     * @return array<string>|null
     */
    public function getClientIds(): ?array
    {
        /** @var array<string>|null $ids */
        $ids = $this->input('clients.ids');

        return $ids;
    }

    /**
     * @return array<string>|null
     */
    public function getTagIds(): ?array
    {
        /** @var array<string>|null $ids */
        $ids = $this->input('tags.ids');

        return $ids;
    }

    /**
     * @return array<string>|null
     */
    public function getTaskIds(): ?array
    {
        /** @var array<string>|null $ids */
        $ids = $this->input('tasks.ids');

        return $ids;
    }

    public function getWeekStartFromUserDefault(Weekday $userWeekStart): Weekday
    {
        if (! $this->filled('weekStart')) {
            return $userWeekStart;
        }

        return Weekday::from(strtolower((string) $this->input('weekStart')));
    }

    private function mapGroup(string $group): TimeEntryAggregationType
    {
        return match ($group) {
            'DAY' => TimeEntryAggregationType::Day,
            'WEEK' => TimeEntryAggregationType::Week,
            'MONTH' => TimeEntryAggregationType::Month,
            'YEAR' => TimeEntryAggregationType::Year,
            'USER' => TimeEntryAggregationType::User,
            'PROJECT' => TimeEntryAggregationType::Project,
            'TASK' => TimeEntryAggregationType::Task,
            'CLIENT' => TimeEntryAggregationType::Client,
            'BILLABLE' => TimeEntryAggregationType::Billable,
            'DESCRIPTION' => TimeEntryAggregationType::Description,
            'TAG' => TimeEntryAggregationType::Tag,
            default => TimeEntryAggregationType::Project,
        };
    }
}
