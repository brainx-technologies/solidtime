<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\MemberGroup;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\MemberGroup;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelModelValidationRules\Rules\UniqueEloquent;

/**
 * @property Organization $organization Organization from model binding
 * @property MemberGroup|null $memberGroup Member group from model binding
 */
class MemberGroupUpdateRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:255',
                UniqueEloquent::make(MemberGroup::class, 'name', function (Builder $builder): Builder {
                    /** @var Builder<MemberGroup> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->ignore($this->memberGroup?->getKey())->withCustomTranslation('validation.member_group_name_already_exists'),
            ],
        ];
    }
}
