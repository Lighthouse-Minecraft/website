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
        // Re-verify the announcement is still published at handle time
        $announcement = Announcement::published()->find($this->announcement->id);

        if (! $announcement) {
            return;
        }

        $service = app(TicketNotificationService::class);

        User::where('membership_level', '>=', MembershipLevel::Traveler->value)
            ->where('id', '!=', $announcement->author_id)
            ->chunk(100, function ($users) use ($service, $announcement) {
                $service->sendToMany(
                    $users,
                    new NewAnnouncementNotification($announcement),
                    'announcements'
                );
            });
    }
}
