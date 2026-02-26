<?php

declare(strict_types=1);

use App\Enums\EmailDigestFrequency;
use App\Models\DiscordAccount;
use App\Models\User;
use App\Notifications\UserReleasedFromBrigNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\Notification;

uses()->group('notifications');

it('always sends immediate email for account category regardless of digest preference', function () {
    $user = User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Daily,
        'last_notification_read_at' => now()->subDays(2),
        'notification_preferences' => ['account' => ['email' => true, 'pushover' => false, 'discord' => false]],
    ]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'account');

    expect($channels)->toContain('mail');
});

it('always sends immediate email for staff_alerts category regardless of digest preference', function () {
    $user = User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Daily,
        'last_notification_read_at' => now()->subDays(2),
        'notification_preferences' => ['staff_alerts' => ['email' => true, 'pushover' => false, 'discord' => false]],
    ]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'staff_alerts');

    expect($channels)->toContain('mail');
});

it('defers ticket emails to digest when user prefers daily and has not visited recently', function () {
    $user = User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Daily,
        'last_notification_read_at' => now()->subDays(2),
        'notification_preferences' => ['tickets' => ['email' => true, 'pushover' => false, 'discord' => false]],
    ]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'tickets');

    expect($channels)->not->toContain('mail');
});

it('includes discord channel when user has linked account and enabled preference', function () {
    $user = User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['account' => ['email' => true, 'pushover' => false, 'discord' => true]],
    ]);
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'account');

    expect($channels)->toContain('discord');
});

it('excludes discord channel when user has no linked account', function () {
    $user = User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['account' => ['email' => true, 'pushover' => false, 'discord' => true]],
    ]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'account');

    expect($channels)->not->toContain('discord');
});

it('returns empty channels when user disables all preferences for a category', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['account' => ['email' => false, 'pushover' => false, 'discord' => false]],
    ]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'account');

    expect($channels)->toBeEmpty();
});

it('uses default preferences when category has no saved preferences', function () {
    $user = User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => [],
    ]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'account');

    // Default for account: email=true, pushover=false, discord=false
    expect($channels)->toContain('mail')
        ->and($channels)->not->toContain('pushover')
        ->and($channels)->not->toContain('discord');
});

it('includes pushover for account category when user has key and preference enabled', function () {
    $user = User::factory()->create([
        'pushover_key' => 'test-key',
        'pushover_monthly_count' => 0,
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['account' => ['email' => true, 'pushover' => true, 'discord' => false]],
    ]);

    $service = new TicketNotificationService;
    $channels = $service->determineChannels($user, 'account');

    expect($channels)->toContain('pushover');
});

it('sends to multiple users with category parameter', function () {
    Notification::fake();

    $users = User::factory()->count(2)->create([
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['account' => ['email' => true, 'pushover' => false, 'discord' => false]],
    ]);

    $notification = new UserReleasedFromBrigNotification($users->first());

    $service = new TicketNotificationService;
    $service->sendToMany($users, $notification, 'account');

    Notification::assertSentTo($users->first(), UserReleasedFromBrigNotification::class);
    Notification::assertSentTo($users->last(), UserReleasedFromBrigNotification::class);
});
