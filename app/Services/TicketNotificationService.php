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
        // Get user's notification preferences
        $preferences = $user->notification_preferences ?? [];
        $ticketPrefs = $preferences['tickets'] ?? ['email' => true, 'pushover' => false];

        // Determine if email should be sent
        $shouldEmail = $ticketPrefs['email'] && $this->shouldSendImmediate($user);

        // Determine if Pushover should be sent
        $shouldPushover = $ticketPrefs['pushover'] && $this->canSendPushover($user);

        // If user doesn't want any notifications, skip
        if (! $shouldEmail && ! $shouldPushover) {
            return;
        }

        // Store preferences temporarily so notification can check them
        $user->_notification_email_allowed = $shouldEmail;
        $user->_notification_pushover_allowed = $shouldPushover;

        // Increment Pushover count if sending
        if ($shouldPushover) {
            $user->incrementPushoverCount();
        }

        // Send notification (notification will check the temporary flags)
        $user->notify($notification);

        // Clean up temporary flags
        unset($user->_notification_email_allowed);
        unset($user->_notification_pushover_allowed);
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
