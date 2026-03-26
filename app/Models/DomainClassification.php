<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClassifiedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $domain
 * @property int $site_type_id
 * @property ClassifiedBy $classified_by
 * @property int|null $rule_id
 * @property int $organization_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SiteType|null $siteType
 */
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
