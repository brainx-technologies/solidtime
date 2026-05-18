<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Organization;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization
 */
class MemberTimeEntryEditOverrideStoreRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'string',
                ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Member> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
            'applies_on' => [
                'required',
                'date_format:Y-m-d',
            ],
            'editable_until' => [
                'required',
                'date',
                'after:now',
            ],
        ];
    }
}
