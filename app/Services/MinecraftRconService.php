<?php

namespace App\Services;

use App\Models\MinecraftCommandLog;
use App\Models\User;
use Thedudeguy\Rcon;

class MinecraftRconService
{
    /**
     * Execute a command on the Minecraft server via RCON
     *
     * @param  string  $command  The command to execute
     * @param  string  $commandType  Type category (e.g., 'whitelist', 'verify', 'ban')
     * @param  string|null  $target  The player or entity affected
     * @param  User|null  $user  The user initiating the command
     * @param  array  $meta  Additional metadata to log
     * @return array ['success' => bool, 'response' => string|null, 'error' => string|null]
     */
    public function executeCommand(
        string $command,
        string $commandType,
        ?string $target = null,
        ?User $user = null,
        array $meta = []
    ): array {
        $startTime = microtime(true);
        $status = 'failed';
        $response = null;
        $errorMessage = null;

        // Create log entry with initial failed status
        $log = MinecraftCommandLog::create([
            'user_id' => $user?->id,
            'command' => $command,
            'command_type' => $commandType,
            'target' => $target,
            'status' => 'failed',
            'ip_address' => request()->ip(),
            'meta' => $meta,
            'executed_at' => now(),
        ]);

        try {
            $rcon = new Rcon(
                config('services.minecraft.rcon_host'),
                config('services.minecraft.rcon_port'),
                config('services.minecraft.rcon_password'),
                3 // timeout in seconds
            );

            if ($rcon->connect()) {
                $response = $rcon->sendCommand($command);
                $status = 'success';
            } else {
                $errorMessage = 'Failed to connect to RCON server';
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Update log with final status
        $log->update([
            'status' => $status,
            'response' => $response,
            'error_message' => $errorMessage,
            'execution_time_ms' => round($executionTime),
        ]);

        return [
            'success' => $status === 'success',
            'response' => $response,
            'error' => $errorMessage,
        ];
    }
}
