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
     *
     * @param  string  $category  The preference category: 'tickets', 'account', or 'staff_alerts'
     */
    public function send(User $user, Notification $notification, string $category = 'tickets'): void
    {
        // Build array of allowed channels based on user preferences
        $channels = $this->determineChannels($user, $category);

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
     *
     * @param  string  $category  The preference category: 'tickets', 'account', or 'staff_alerts'
     */
    public function determineChannels(User $user, string $category = 'tickets'): array
    {
        $channels = [];

        // Get user's notification preferences for this category
        $preferences = $user->notification_preferences ?? [];
        $categoryPrefs = $preferences[$category] ?? $this->defaultPreferences($category);

        // Add email — for tickets, respect digest frequency; for others, always send immediately
        if ($categoryPrefs['email'] ?? true) {
            if ($category === 'tickets' && ! $this->shouldSendImmediate($user)) {
                // Ticket emails deferred to digest
            } else {
                $channels[] = 'mail';
            }
        }

        // Add Pushover if user wants it and can receive it
        if (($categoryPrefs['pushover'] ?? false) && $this->canSendPushover($user)) {
            $channels[] = 'pushover';
        }

        // Add Discord if user wants it and has a linked account
        if (($categoryPrefs['discord'] ?? false) && $user->hasDiscordLinked()) {
            $channels[] = 'discord';
        }

        return $channels;
    }

    /**
     * Default preferences per category
     */
    protected function defaultPreferences(string $category): array
    {
        return match ($category) {
            'account' => ['email' => true, 'pushover' => false, 'discord' => false],
            'staff_alerts' => ['email' => true, 'pushover' => false, 'discord' => false],
            default => ['email' => true, 'pushover' => false, 'discord' => false],
        };
    }

    /**
     * Send notification to multiple users
     */
    public function sendToMany(iterable $users, Notification $notification, string $category = 'tickets'): void
    {
        foreach ($users as $user) {
            $this->send($user, $notification, $category);
        }
    }
}
