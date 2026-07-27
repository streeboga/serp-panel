<?php

declare(strict_types=1);

return [
    /*
     * How deep to collect the SERP. Positions beyond this are reported as
     * "not in top", so this is the ceiling of position monitoring.
     */
    'depth' => (int) env('SERP_DEPTH', 100),

    /*
     * Measure real phrase/exact volumes via Wordstat operators. Each phrase then
     * costs 3 API calls instead of 1, against a 100 req/hour quota.
     */
    'wordstat_precise' => (bool) env('WORDSTAT_PRECISE', false),

    /*
     * Yandex Wordstat API hourly quota. The drip sizes each run from this so the
     * quota is never exceeded, whether or not precise collection is on.
     */
    'wordstat_quota_per_hour' => (int) env('WORDSTAT_QUOTA_PER_HOUR', 100),
];
