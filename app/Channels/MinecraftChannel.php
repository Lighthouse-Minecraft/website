<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class MinecraftChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        $notification->toMinecraft($notifiable);
    }
}
