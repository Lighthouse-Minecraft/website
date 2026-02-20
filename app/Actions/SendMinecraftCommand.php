<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\MinecraftCommandNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class SendMinecraftCommand
{
    use AsAction;

    /**
     * Send a command to the Minecraft server (queued or synchronous)
     *
     * @param  string  $command  The RCON command to execute
     * @param  string  $commandType  Type category (e.g., 'whitelist', 'verify', 'ban')
     * @param  string|null  $target  The player or entity affected
     * @param  User|null  $user  The user initiating the command
     * @param  array  $meta  Additional metadata to log
     * @param  bool  $async  Whether to queue the command (default: true)
     */
    public function handle(
        string $command,
        string $commandType,
        ?string $target = null,
        ?User $user = null,
        array $meta = [],
        bool $async = true
    ): void {
        if ($async) {
            // Dispatch as a queued notification
            \Illuminate\Support\Facades\Notification::route('minecraft', 'server')
                ->notify(new MinecraftCommandNotification($command, $commandType, $target, $user, $meta));
        } else {
            // Execute synchronously
            $rconService = app(\App\Services\MinecraftRconService::class);
            $rconService->executeCommand($command, $commandType, $target, $user, $meta);
        }
    }

    /**
     * Dispatch command asynchronously (for use with queued jobs)
     */
    public static function dispatch(
        string $command,
        string $commandType,
        ?string $target = null,
        ?User $user = null,
        array $meta = []
    ): void {
        static::run($command, $commandType, $target, $user, $meta, true);
    }
}
