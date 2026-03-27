<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PositionAlertTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $alertId,
        public readonly int $keywordId,
        public readonly int $oldPosition,
        public readonly int $newPosition,
    ) {}
}
