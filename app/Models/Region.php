<?php

namespace App\Models;

use App\Enums\Engine;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['engine', 'code', 'name', 'yandex_lr', 'google_gl', 'google_hl'];

    protected $casts = [
        'engine' => Engine::class,
    ];
}
