<?php

declare(strict_types=1);

namespace App\Services\Audit\DTO;

use App\Enums\CheckGroup;
use App\Enums\Severity;

final readonly class Finding
{
    public function __construct(
        public string $check,
        public CheckGroup $group,
        public Severity $severity,
        public string $message,
        public mixed $value = null,
        public mixed $expected = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'group' => $this->group->value,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'value' => $this->value,
            'expected' => $this->expected,
        ];
    }
}
