<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\MemberGroup;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization Organization from model binding
 * @property MemberGroup|null $memberGroup Member group from model binding
 */
class MemberGroupSyncMembersRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'member_ids' => [
                'present',
                'array',
            ],
            'member_ids.*' => [
                'string',
                ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Member> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function getMemberIds(): array
    {
        $value = $this->validated('member_ids');

        return is_array($value) ? array_values(array_unique($value)) : [];
    }
}
