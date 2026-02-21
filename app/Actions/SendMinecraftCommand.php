<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\MinecraftCommandNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class SendMinecraftCommand
{
    use AsAction;

    /**
     * Send a command to the Minecraft server, either queued or executed immediately.
     *
     * In local environments this method forces immediate execution (bypasses the queue).
     * When `$async` is true the command is dispatched for queued processing; when false the command is executed synchronously.
     *
     * @param string $command The RCON command to execute.
     * @param string $commandType Category of the command (e.g., 'whitelist', 'verify', 'ban').
     * @param string|null $target Optional player or entity target for the command.
     * @param User|null $user Optional user that initiated the command.
     * @param array $meta Optional additional metadata to include with the command.
     * @param bool $async Whether to queue the command (`true`) or execute it immediately (`false`).
     */
    public function handle(
        string $command,
        string $commandType,
        ?string $target = null,
        ?User $user = null,
        array $meta = [],
        bool $async = true
    ): void {
        // In local dev, bypass the queue so FakeMinecraftRconService fires immediately
        // without needing a queue worker running.
        if (app()->isLocal()) {
            $async = false;
        }

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