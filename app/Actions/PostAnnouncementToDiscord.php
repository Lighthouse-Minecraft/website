<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Services\DiscordApiService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class PostAnnouncementToDiscord
{
    use AsAction;

    public function handle(Announcement $announcement): bool
    {
        $channelId = config('services.discord.announcements_channel_id');

        if (! $channelId) {
            return false;
        }

        $url = route('dashboard');
        $content = "## {$announcement->title}\n\n{$announcement->content}\n\n{$url}";

        // Discord message limit is 2000 characters
        if (mb_strlen($content) > 2000) {
            $suffix = "...\n\n{$url}";
            $available = max(0, 2000 - mb_strlen($suffix));
            $content = mb_substr($content, 0, $available).$suffix;
        }

        try {
            $sent = app(DiscordApiService::class)->sendChannelMessage($channelId, $content);

            if (! $sent) {
                Log::warning('Failed to post announcement to Discord channel', [
                    'announcement_id' => $announcement->id,
                ]);
            }

            return $sent;
        } catch (\Exception $e) {
            Log::warning('Failed to post announcement to Discord channel', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
