<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Send daily ticket digests at 8am
Schedule::command('tickets:send-digests daily')
    ->dailyAt('08:00')
    ->runInBackground();

// Send weekly ticket digests on Mondays at 8am
Schedule::command('tickets:send-digests weekly')
    ->weeklyOn(1, '08:00')
    ->runInBackground();

// Cleanup expired Minecraft verifications every 5 minutes
Schedule::command('minecraft:cleanup-expired')
    ->everyFiveMinutes()
    ->runInBackground();

// Refresh Minecraft usernames daily at 3am (30-day staggered cycle)
Schedule::command('minecraft:refresh-usernames')
    ->dailyAt('03:00')
    ->runInBackground();

// Check for expired brig timers and notify users daily at 9am
Schedule::command('brig:check-timers')
    ->dailyAt('09:00')
    ->runInBackground();
