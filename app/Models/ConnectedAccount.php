<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $type
 * @property string $label
 * @property array|null $credentials
 * @property bool $is_active
 * @property \Carbon\Carbon|null $expires_at
 */
class ConnectedAccount extends Model
{
    protected $fillable = [
        'organization_id', 'type', 'label', 'credentials', 'is_active', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
