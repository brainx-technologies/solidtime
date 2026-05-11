<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Member;

use App\Enums\Role;
use App\Http\Requests\V1\BaseFormRequest;
use App\Models\MemberGroup;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization
 */
class MemberIndexRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'page' => [
                'integer',
                'min:1',
                'max:2147483647',
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'role' => [
                'nullable',
                Rule::enum(Role::class),
            ],
            'group_id' => [
                'nullable',
                'string',
                ExistsEloquent::make(MemberGroup::class, null, function (Builder $builder): Builder {
                    /** @var Builder<MemberGroup> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
        ];
    }

    public function getSearch(): ?string
    {
        $value = $this->validated('search');
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    public function getRole(): ?Role
    {
        $value = $this->validated('role');
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Role::from($value);
    }

    public function getGroupId(): ?string
    {
        $value = $this->validated('group_id');
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
