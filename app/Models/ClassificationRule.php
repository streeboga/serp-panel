<?php

namespace App\Models;

use App\Enums\ClassificationRuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
