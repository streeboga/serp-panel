<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClassificationRuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property ClassificationRuleType $rule_type
 * @property string $pattern
 * @property int $site_type_id
 * @property int $priority
 * @property bool $is_system
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ClassificationRule extends Model
{
    protected $fillable = [
        'organization_id', 'rule_type', 'pattern',
        'site_type_id', 'priority', 'is_system',
    ];

    protected $casts = [
        'rule_type' => ClassificationRuleType::class,
        'is_system' => 'boolean',
        'priority' => 'integer',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<SiteType, $this> */
    public function siteType(): BelongsTo
    {
        return $this->belongsTo(SiteType::class);
    }
}
