<?php

declare(strict_types=1);

use App\Actions\AcknowledgeAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use Livewire\Livewire;

uses()->group('announcements', 'dashboard');

it('shows only the newest unacknowledged announcement on dashboard', function () {
    $user = User::factory()->create();
    loginAs($user);

    $older = Announcement::factory()->published()->create([
        'published_at' => now()->subDays(2),
        'notifications_sent_at' => now(),
    ]);
    $newer = Announcement::factory()->published()->create([
        'published_at' => now()->subDay(),
        'notifications_sent_at' => now(),
    ]);

    Livewire::test('dashboard.view-announcements')
        ->assertSee($newer->title)
        ->assertDontSee($older->title);
});

it('hides banner after acknowledging the newest announcement', function () {
    $user = User::factory()->create();
    loginAs($user);

    $announcement = Announcement::factory()->published()->create([
        'notifications_sent_at' => now(),
    ]);
    AcknowledgeAnnouncement::run($announcement, $user);

    Livewire::test('dashboard.view-announcements')
        ->assertDontSee($announcement->title);
});

it('does not show expired announcements in banner', function () {
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create([
        'expired_at' => now()->subHour(),
        'notifications_sent_at' => now(),
    ]);

    Livewire::test('dashboard.view-announcements')
        ->assertSet('latestAnnouncement', null);
});
