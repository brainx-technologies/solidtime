<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Organization;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @property Organization $organization
 */
class OrganizationTimeEntryEditPolicyStoreRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'enabled' => [
                'required',
                'boolean',
            ],
            'lock_after_days' => [
                'required',
                'integer',
                'min:1',
                'max:365',
            ],
            'cutoff_time' => [
                'required',
                'date_format:H:i',
            ],
            'timezone' => [
                'required',
                'timezone',
            ],
        ];
    }
}
