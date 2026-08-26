<?php

declare(strict_types=1);

namespace App\Services\Audit\DTO;

final readonly class FetchedPage
{
    /**
     * @param  array<string, array<int, string>>  $headers
     * @param  array<int, array{url: string, status: int}>  $redirectChain
     */
    public function __construct(
        public string $requestedUrl,
        public string $finalUrl,
        public int $status,
        public array $headers,
        public string $body,
        public array $redirectChain,
        public int $responseTimeMs,
    ) {}

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (mb_strtolower($key) === mb_strtolower($name)) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    public function isHtml(): bool
    {
        return str_contains(mb_strtolower($this->header('content-type') ?? ''), 'html');
    }
}
