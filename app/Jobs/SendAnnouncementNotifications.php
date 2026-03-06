<?php

namespace App\Jobs;

use App\Enums\MembershipLevel;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use App\Services\TicketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAnnouncementNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Announcement $announcement
    ) {}

    public function handle(): void
    {
        $service = app(TicketNotificationService::class);

        User::where('membership_level', '>=', MembershipLevel::Traveler->value)
            ->where('id', '!=', $this->announcement->author_id)
            ->chunk(100, function ($users) use ($service) {
                $service->sendToMany(
                    $users,
                    new NewAnnouncementNotification($this->announcement),
                    'announcements'
                );
            });
    }
}
