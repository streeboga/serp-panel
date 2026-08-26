<?php

declare(strict_types=1);

namespace SerpAudit\Checks;

use SerpAudit\Contracts\PageCheck;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;
use SerpAudit\Thresholds;

abstract class Check implements PageCheck
{
    public function __construct(
        protected readonly Thresholds $thresholds = new Thresholds,
    ) {}

    /** @return array<string, mixed> */
    public function metrics(PageContext $context): array
    {
        return [];
    }

    protected function finding(
        string $issue,
        Severity $severity,
        string $message,
        mixed $value = null,
        mixed $expected = null,
    ): Finding {
        return Finding::from($this, $issue, $severity, $message, $value, $expected);
    }
}
