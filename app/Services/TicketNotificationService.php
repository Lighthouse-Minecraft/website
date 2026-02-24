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
        // Build array of allowed channels based on user preferences
        $channels = $this->determineChannels($user);

        // If user doesn't want any notifications, skip
        if (empty($channels)) {
            return;
        }

        // Increment Pushover count if sending via Pushover
        if (in_array('pushover', $channels)) {
            $user->incrementPushoverCount();
        }

        // Pass channels to notification and send
        $notification->setChannels($channels, $user->pushover_key);
        $user->notify($notification);
    }

    /**
     * Determine which channels should be used for this user
     */
    public function determineChannels(User $user): array
    {
        $channels = [];

        // Get user's notification preferences
        $preferences = $user->notification_preferences ?? [];
        $ticketPrefs = $preferences['tickets'] ?? ['email' => true, 'pushover' => false];

        // Add email if user wants it and should receive immediate notification
        if ($ticketPrefs['email'] && $this->shouldSendImmediate($user)) {
            $channels[] = 'mail';
        }

        // Add Pushover if user wants it and can receive it
        if ($ticketPrefs['pushover'] && $this->canSendPushover($user)) {
            $channels[] = 'pushover';
        }

        // Add Discord if user wants it and has a linked account
        if (($ticketPrefs['discord'] ?? false) && $user->hasDiscordLinked()) {
            $channels[] = 'discord';
        }

        return $channels;
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
