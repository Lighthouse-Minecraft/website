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

// Send pre-meeting report reminders daily at 8am
Schedule::command('meetings:send-report-reminders')
    ->dailyAt('08:00')
    ->runInBackground();

// Process age-based transitions (13, 19) daily at 2am
Schedule::command('parent-portal:process-age-transitions')
    ->dailyAt('02:00')
    ->runInBackground();

// Activate/archive community questions based on schedule
Schedule::command('community:process-schedule')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Purge images from closed tickets and locked discussions
Schedule::command('messages:purge-images')
    ->dailyAt('04:00')
    ->runInBackground();

// Publish scheduled blog posts
Schedule::job(new \App\Jobs\PublishScheduledPosts)
    ->everyMinute();

// Escalate unassigned tickets past the configured threshold
Schedule::job(new \App\Jobs\EscalateUnassignedTickets)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

// Cleanup unreferenced blog images monthly
Schedule::job(new \App\Jobs\CleanupUnreferencedBlogImages)
    ->monthly();

// Check rules agreement compliance daily and send reminders / place brigs
Schedule::job(new \App\Jobs\CheckRulesAgreementJob)
    ->dailyAt('07:00')
    ->withoutOverlapping(10)
    ->onOneServer();

// Create daily database backup at 3:00 AM
Schedule::command('app:backup-create')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Clean up local backup files past the retention window daily at 4:00 AM
Schedule::command('app:backup-cleanup')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Push most recent local backup to S3 every 3 days at 3:30 AM
Schedule::command('app:backup-push-s3')
    ->cron('30 3 */3 * *')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
