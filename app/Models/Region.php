<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Engine $engine
 * @property string $code
 * @property string $name
 * @property int|null $yandex_lr
 * @property string|null $google_gl
 * @property string|null $google_hl
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Region extends Model
{
    protected $fillable = ['engine', 'code', 'name', 'yandex_lr', 'google_gl', 'google_hl'];

    protected $casts = [
        'engine' => Engine::class,
    ];
}
