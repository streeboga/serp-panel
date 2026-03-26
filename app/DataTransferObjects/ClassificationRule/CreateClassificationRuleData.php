<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ClassificationRule;

use Spatie\LaravelData\Data;

final class CreateClassificationRuleData extends Data
{
    public function __construct(
        public readonly string $rule_type,
        public readonly string $pattern,
        public readonly int $site_type_id,
        public readonly int $priority,
        public readonly ?bool $is_system = null,
    ) {}
}
