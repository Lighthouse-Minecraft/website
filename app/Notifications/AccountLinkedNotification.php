<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLinkedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public User $user,
        public string $accountName,
        public string $accountType,
        public int $activeMinecraftCount,
        public int $disabledMinecraftCount,
        public int $activeDiscordCount,
        public int $disabledDiscordCount,
    ) {}

    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        if (in_array('mail', $this->allowedChannels)) {
            $channels[] = 'mail';
        }

        if (in_array('pushover', $this->allowedChannels) && $this->pushoverKey) {
            $channels[] = PushoverChannel::class;
        }

        if (in_array('discord', $this->allowedChannels)) {
            $channels[] = DiscordChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = "{$this->user->name} has added a new {$this->accountType} account.";

        return (new MailMessage)
            ->subject($subject)
            ->line($subject)
            ->line("Account name: **{$this->accountName}**")
            ->line(
                "They currently have {$this->activeMinecraftCount} active Minecraft account(s) and ".
                "{$this->disabledMinecraftCount} disabled Minecraft account(s), and ".
                "{$this->activeDiscordCount} active Discord account(s) and ".
                "{$this->disabledDiscordCount} disabled Discord account(s)."
            )
            ->action('View Profile', route('profile.show', $this->user));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => "New {$this->accountType} Account Linked",
            'message' => "{$this->user->name} linked {$this->accountType} account: {$this->accountName}. ".
                "MC: {$this->activeMinecraftCount} active / {$this->disabledMinecraftCount} disabled. ".
                "Discord: {$this->activeDiscordCount} active / {$this->disabledDiscordCount} disabled.",
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**{$this->user->name} has added a new {$this->accountType} account.**\n".
            "Account name: **{$this->accountName}**\n".
            "MC: {$this->activeMinecraftCount} active / {$this->disabledMinecraftCount} disabled | ".
            "Discord: {$this->activeDiscordCount} active / {$this->disabledDiscordCount} disabled";
    }
}
