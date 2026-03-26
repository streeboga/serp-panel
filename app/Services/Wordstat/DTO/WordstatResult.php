<?php

declare(strict_types=1);

namespace App\Services\Wordstat\DTO;

final readonly class WordstatResult
{
    /**
     * @param  array{exact: int, broad: int, phrase: int}  $frequencies
     * @param  array<int, int>  $trends  key = Unix timestamp of month start, value = absolute count
     * @param  array<array{suggestion: string, frequency: int, type: string}>  $suggestions
     */
    public function __construct(
        public array $frequencies,
        public array $trends = [],
        public array $suggestions = [],
    ) {}
}
