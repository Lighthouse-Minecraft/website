<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class PushoverChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPushover')) {
            return;
        }

        $message = $notification->toPushover($notifiable);

        // Prefer notification-provided key, fall back to notifiable's key
        $pushoverKey = method_exists($notification, 'getPushoverKey')
            ? ($notification->getPushoverKey() ?? $notifiable->pushover_key ?? null)
            : ($notifiable->pushover_key ?? null);

        if (! $message || ! $pushoverKey) {
            return;
        }

        $token = config('services.pushover.token');

        if (! $token) {
            return;
        }

        Http::asForm()->post('https://api.pushover.net/1/messages.json', [
            'token' => $token,
            'user' => $pushoverKey,
            'message' => $message['message'] ?? '',
            'title' => $message['title'] ?? config('app.name'),
            'url' => $message['url'] ?? null,
            'priority' => $message['priority'] ?? 0,
        ]);
    }
}
