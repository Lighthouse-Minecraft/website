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

        if (! $message || ! $notifiable->pushover_key) {
            return;
        }

        $token = config('services.pushover.token');

        if (! $token) {
            return;
        }

        Http::asForm()->post('https://api.pushover.net/1/messages.json', [
            'token' => $token,
            'user' => $notifiable->pushover_key,
            'message' => $message['message'] ?? '',
            'title' => $message['title'] ?? config('app.name'),
            'url' => $message['url'] ?? null,
            'priority' => $message['priority'] ?? 0,
        ]);
    }
}
