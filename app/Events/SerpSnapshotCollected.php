<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SerpSnapshotCollected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $snapshotId,
        public readonly int $keywordId,
        public readonly int $organizationId,
        public readonly string $collectedAt,
    ) {}
}
