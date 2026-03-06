<?php

declare(strict_types=1);

use App\Jobs\SendAnnouncementNotifications;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses()->group('announcements', 'notifications');

it('dispatches notification job on dashboard load for newly published announcement', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create(['notifications_sent_at' => null]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertPushed(SendAnnouncementNotifications::class);
});

it('does not dispatch notification job for announcement already notified', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create(['notifications_sent_at' => now()]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertNotPushed(SendAnnouncementNotifications::class);
});

it('does not dispatch notification job for draft announcements', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->unpublished()->create(['notifications_sent_at' => null]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertNotPushed(SendAnnouncementNotifications::class);
});

it('sets notifications_sent_at when dispatching', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    $announcement = Announcement::factory()->published()->create(['notifications_sent_at' => null]);

    Livewire::test('dashboard.view-announcements');

    expect($announcement->fresh()->notifications_sent_at)->not->toBeNull();
});

it('does not dispatch for expired announcements', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create([
        'notifications_sent_at' => null,
        'expired_at' => now()->subHour(),
    ]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertNotPushed(SendAnnouncementNotifications::class);
});
