<?php

declare(strict_types=1);

return [
    /*
     * How deep to collect the SERP. Positions beyond this are reported as
     * "not in top", so this is the ceiling of position monitoring.
     */
    'depth' => (int) env('SERP_DEPTH', 100),
];
