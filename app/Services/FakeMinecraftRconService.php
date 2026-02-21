<?php

namespace App\Services;

use App\Models\MinecraftCommandLog;
use App\Models\User;

class FakeMinecraftRconService extends MinecraftRconService
{
    /**
     * Simulate execution of a Minecraft RCON command and record a corresponding command log for local development.
     *
     * Creates a log entry marked as simulated (meta + ['simulated' => true]), records the request IP and execution timestamp,
     * and updates the log with a simulated response and execution time. Intended for use in a local environment (APP_ENV=local).
     *
     * @param  string  $command  The raw command to simulate.
     * @param  string  $commandType  A type or category describing the command.
     * @param  string|null  $target  Optional target identifier for the command (e.g., server, entity).
     * @param  \App\Models\User|null  $user  Optional user initiating the command; used to set the log's user_id.
     * @param  array  $meta  Additional metadata to attach to the command log; will be merged with ['simulated' => true].
     * @return array An associative array with keys:
     *               - 'success' => `true` if the simulation succeeded, `false` otherwise.
     *               - 'response' => the simulated response string.
     *               - 'error' => an error message or `null` when there is no error.
     */
    public function executeCommand(
        string $command,
        string $commandType,
        ?string $target = null,
        ?User $user = null,
        array $meta = []
    ): array {
        $startTime = microtime(true);
        $response = '[local] Simulated: '.$command;

        MinecraftCommandLog::create([
            'user_id' => $user?->id,
            'command' => $command,
            'command_type' => $commandType,
            'target' => $target,
            'status' => 'success',
            'response' => $response,
            'error_message' => null,
            'ip_address' => request()->ip(),
            'meta' => array_merge($meta, ['simulated' => true]),
            'executed_at' => now(),
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000),
        ]);

        return [
            'success' => true,
            'response' => $response,
            'error' => null,
        ];
    }
}
