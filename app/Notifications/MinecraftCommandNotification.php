<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MinecraftCommandNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900]; // 1 min, 5 mins, 15 mins

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $command,
        public string $commandType,
        public ?string $target = null,
        public ?User $user = null,
        public array $meta = []
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['minecraft'];
    }

    /**
     * Send the command via RCON
     */
    public function toMinecraft($notifiable)
    {
        $rconService = app(MinecraftRconService::class);

        return $rconService->executeCommand(
            $this->command,
            $this->commandType,
            $this->target,
            $this->user,
            $this->meta
        );
    }
}
