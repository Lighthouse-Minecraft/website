<?php

namespace App\Services;

use App\Enums\EmailDigestFrequency;
use App\Models\User;
use Illuminate\Notifications\Notification;

class TicketNotificationService
{
    /**
     * Determine if a user should receive immediate notification
     */
    public function shouldSendImmediate(User $user): bool
    {
        // If user prefers digest, check if they recently visited
        if ($user->email_digest_frequency !== EmailDigestFrequency::Immediate) {
            // If they haven't visited since last notification, use digest
            if (! $user->last_notification_read_at || $user->last_notification_read_at->lt(now()->subHour())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if user can receive Pushover notification
     */
    public function canSendPushover(User $user): bool
    {
        return $user->canSendPushover();
    }

    /**
     * Send notification via appropriate channels
     */
    public function send(User $user, Notification $notification): void
    {
        $channels = [];

        // Determine email channel
        if ($this->shouldSendImmediate($user)) {
            $channels[] = 'mail';
        }

        // Add Pushover if available
        if ($this->canSendPushover($user)) {
            $channels[] = 'pushover';
            $user->incrementPushoverCount();
        }

        // Send notification
        if (! empty($channels)) {
            $user->notify($notification);
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToMany(iterable $users, Notification $notification): void
    {
        foreach ($users as $user) {
            $this->send($user, $notification);
        }
    }
}
