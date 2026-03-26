<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WordstatSuggestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $keyword_id
 * @property string $suggestion
 * @property int $frequency
 * @property WordstatSuggestionType $type
 * @property Carbon $collected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WordstatSuggestion extends Model
{
    protected $fillable = ['keyword_id', 'suggestion', 'frequency', 'type', 'collected_at'];

    protected $casts = [
        'type' => WordstatSuggestionType::class,
        'collected_at' => 'date',
        'frequency' => 'integer',
    ];

    /** @return BelongsTo<Keyword, $this> */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
