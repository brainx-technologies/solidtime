<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\TimeEntry;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Service\PermissionStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization Organization from model binding
 */
class TimeEntryUpdateRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            // ID of the organization member that the time entry should belong to
            'member_id' => [
                'string',
                ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Member> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
            // ID of the project that the time entry should belong to
            'project_id' => [
                'sometimes',
                'nullable',
                'string',
                ExistsEloquent::make(Project::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Project> $builder */
                    $builder = $builder->whereBelongsTo($this->organization, 'organization');

                    // If user doesn't have 'all' permission for time entries or projects, only allow access to public projects or projects they're a member of
                    $permissionStore = app(PermissionStore::class);
                    if (! $permissionStore->has($this->organization, 'time-entries:update:all')
                        && ! $permissionStore->has($this->organization, 'projects:view:all')) {
                        $builder = $builder->visibleByEmployee(Auth::user());
                    }

                    return $builder;
                })->uuid(),
            ],
            // ID of the task that the time entry should belong to
            'task_id' => [
                'nullable',
                'string',
                ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Task> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
                ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Task> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization')
                        ->where('project_id', $this->input('project_id'));
                })->uuid()->withMessage(__('validation.task_belongs_to_project')),
            ],
            // Start of time entry (Format: "Y-m-d\TH:i:s\Z", UTC timezone, Example: "2000-02-22T14:58:59Z")
            'start' => [
                'date_format:Y-m-d\TH:i:s\Z',
            ],
            // End of time entry (Format: "Y-m-d\TH:i:s\Z", UTC timezone, Example: "2000-02-22T14:58:59Z")
            'end' => [
                'nullable',
                'date_format:Y-m-d\TH:i:s\Z',
                'after_or_equal:start',
            ],
            // Whether time entry is billable
            'billable' => [
                'boolean',
            ],
            // Description of time entry
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            // List of tag IDs
            'tags' => [
                'nullable',
                'array',
            ],
            'tags.*' => [
                'string',
                ExistsEloquent::make(Tag::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Tag> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['project_id', 'description'])) {
                return;
            }

            /** @var TimeEntry $timeEntry */
            $timeEntry = $this->route('timeEntry');

            if ($this->has('project_id')) {
                $projectId = $this->input('project_id');
                if ($projectId === '') {
                    $projectId = null;
                }

                if (filled($timeEntry->project_id) && ! filled($projectId)) {
                    $validator->errors()->add(
                        'project_id',
                        __('validation.time_entry_project_cannot_be_removed', [
                            'attribute' => __('validation.entities.project'),
                        ]),
                    );
                }
            }

            if ($this->has('description')) {
                if (filled($timeEntry->description) && ! filled($this->input('description'))) {
                    $validator->errors()->add(
                        'description',
                        __('validation.time_entry_description_cannot_be_removed', [
                            'attribute' => 'description',
                        ]),
                    );
                }
            }
        });
    }
}
