<?php

declare(strict_types=1);

namespace App\Enums;

enum Severity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Notice = 'notice';

    /** Вес находки в штрафе к оценке 0-100. */
    public function penalty(): int
    {
        return match ($this) {
            self::Critical => 10,
            self::Warning => 3,
            self::Notice => 1,
        };
    }
}
