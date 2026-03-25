<?php

namespace App\Models;

use App\Enums\ClassifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainClassification extends Model
{
    protected $fillable = [
        'domain', 'site_type_id', 'classified_by',
        'rule_id', 'organization_id',
    ];

    protected $casts = [
        'classified_by' => ClassifiedBy::class,
    ];

    /** @return BelongsTo<SiteType, $this> */
    public function siteType(): BelongsTo
    {
        return $this->belongsTo(SiteType::class);
    }

    /** @return BelongsTo<ClassificationRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ClassificationRule::class, 'rule_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
