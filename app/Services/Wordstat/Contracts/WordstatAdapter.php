<?php

declare(strict_types=1);

namespace App\Services\Wordstat\Contracts;

use App\Services\Wordstat\DTO\WordstatResult;

interface WordstatAdapter
{
    public function collect(string $keyword, int $regionId, bool $withTrends = true): WordstatResult;

    public function healthCheck(): bool;
}
