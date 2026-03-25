<?php

namespace App\Models;

use App\Enums\WordstatSuggestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
