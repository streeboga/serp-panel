<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Device;
use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property string $pageable_type
 * @property int $pageable_id
 * @property Engine|null $engine
 * @property Device|null $device
 * @property int|null $priority
 * @property bool $is_target
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Page $page
 * @property-read Model $pageable
 */
final class Pageable extends Model
{
    protected $table = 'pageables';

    protected $fillable = [
        'page_id', 'pageable_type', 'pageable_id',
        'engine', 'device', 'priority', 'is_target',
    ];

    protected $casts = [
        'engine' => Engine::class,
        'device' => Device::class,
        'is_target' => 'boolean',
    ];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return MorphTo<Model, $this> */
    public function pageable(): MorphTo
    {
        return $this->morphTo();
    }
}
