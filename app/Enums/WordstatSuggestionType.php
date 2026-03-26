<?php

declare(strict_types=1);

namespace App\Enums;

enum WordstatSuggestionType: string
{
    case Suggestion = 'suggestion';
    case Association = 'association';
}
