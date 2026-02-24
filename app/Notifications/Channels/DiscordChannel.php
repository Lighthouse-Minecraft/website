<?php

namespace App\Notifications\Channels;

use App\Services\DiscordApiService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class DiscordChannel
{
    /**
     * Send the given notification via Discord DM.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDiscord')) {
            return;
        }

        $message = $notification->toDiscord($notifiable);

        if (! $message) {
            return;
        }

        $discordApi = app(DiscordApiService::class);

        foreach ($notifiable->discordAccounts()->active()->get() as $account) {
            try {
                $discordApi->sendDirectMessage($account->discord_user_id, $message);
            } catch (\Exception $e) {
                Log::warning('Failed to send Discord DM', [
                    'discord_user_id' => $account->discord_user_id,
                    'user_id' => $notifiable->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
