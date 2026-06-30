<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('schedules:check')->everyMinute();
Schedule::command('scrape-jobs:dispatch')->everyMinute();
Schedule::command('partitions:create')->daily();
Schedule::command('cleanup:raw-responses')->daily();
// Drip Wordstat collection so it stays within the 100 req/hour quota and always
// works the stalest phrases first (wordstat:check remains for manual full runs).
Schedule::command('wordstat:drip')->everyFifteenMinutes();
