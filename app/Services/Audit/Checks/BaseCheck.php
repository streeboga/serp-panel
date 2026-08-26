<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Enums\Severity;
use App\Services\Audit\Contracts\PageCheck;
use App\Services\Audit\DTO\Finding;

abstract class BaseCheck implements PageCheck
{
    protected function finding(
        string $check,
        Severity $severity,
        string $message,
        mixed $value = null,
        mixed $expected = null,
    ): Finding {
        return new Finding($check, $this->group(), $severity, $message, $value, $expected);
    }

    protected function threshold(string $key): int|float
    {
        return config("audit.thresholds.{$key}");
    }
}
