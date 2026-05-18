<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $organization_id
 * @property bool $enabled
 * @property int $lock_after_days
 * @property string $cutoff_time
 * @property string $timezone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
class OrganizationTimeEntryEditPolicy extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'enabled',
        'lock_after_days',
        'cutoff_time',
        'timezone',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'lock_after_days' => 'integer',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
