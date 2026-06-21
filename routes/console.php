<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leads:run-distribution')->everyFiveMinutes();
Schedule::command('leads:recalculate-scores')->daily();
Schedule::command('leads:evaluate-sla')->everyFiveMinutes();
Schedule::command('leads:archive-stale')->monthly();
Schedule::command('leads:send-sla-digest')->dailyAt('08:00');
