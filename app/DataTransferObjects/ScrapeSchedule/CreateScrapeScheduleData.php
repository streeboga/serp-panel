<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ScrapeSchedule;

use Spatie\LaravelData\Data;

final class CreateScrapeScheduleData extends Data
{
    public function __construct(
        public readonly int $scraper_id,
        public readonly int $frequency_days,
        public readonly ?int $project_id = null,
        public readonly ?int $category_id = null,
        public readonly ?int $cluster_id = null,
        public readonly ?int $keyword_id = null,
        public readonly ?bool $is_active = null,
    ) {}
}
