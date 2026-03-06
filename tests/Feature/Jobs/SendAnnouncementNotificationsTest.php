<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Jobs\SendAnnouncementNotifications;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('announcements', 'jobs', 'notifications');

it('sends notification to all traveler+ users except the author', function () {
    Notification::fake();
    $author = User::factory()->create(['membership_level' => MembershipLevel::Traveler->value]);
    $recipient = User::factory()->create(['membership_level' => MembershipLevel::Traveler->value]);
    $stowaway = User::factory()->create(['membership_level' => MembershipLevel::Stowaway->value]);

    $announcement = Announcement::factory()->published()->create(['author_id' => $author->id]);

    (new SendAnnouncementNotifications($announcement))->handle();

    Notification::assertSentTo($recipient, NewAnnouncementNotification::class);
    Notification::assertNotSentTo($author, NewAnnouncementNotification::class);
    Notification::assertNotSentTo($stowaway, NewAnnouncementNotification::class);
});
