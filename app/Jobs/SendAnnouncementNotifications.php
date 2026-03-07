<?php

namespace App\Jobs;

use App\Enums\MembershipLevel;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use App\Services\DiscordApiService;
use App\Services\TicketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        $this->postToDiscordChannel($announcement);
    }

    protected function postToDiscordChannel(Announcement $announcement): void
    {
        $channelId = config('services.discord.announcements_channel_id');

        if (! $channelId) {
            return;
        }

        $url = route('dashboard');
        $content = "## {$announcement->title}\n\n{$announcement->content}\n\n{$url}";

        // Discord message limit is 2000 characters
        if (mb_strlen($content) > 2000) {
            $suffix = "...\n\n{$url}";
            $content = mb_substr($content, 0, 2000 - mb_strlen($suffix)).$suffix;
        }

        try {
            app(DiscordApiService::class)->sendChannelMessage($channelId, $content);
        } catch (\Exception $e) {
            Log::warning('Failed to post announcement to Discord channel', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
