<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ClassificationRule;

use Spatie\LaravelData\Data;

final class UpdateClassificationRuleData extends Data
{
    public function __construct(
        public readonly ?string $rule_type = null,
        public readonly ?string $pattern = null,
        public readonly ?int $site_type_id = null,
        public readonly ?int $priority = null,
        public readonly ?bool $is_system = null,
    ) {}
}
