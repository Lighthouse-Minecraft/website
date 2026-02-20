<?php

namespace App\Services;

use App\Models\MinecraftCommandLog;
use App\Models\User;

class FakeMinecraftRconService extends MinecraftRconService
{
    /**
     * Simulate a Minecraft RCON command without connecting to a real server.
     *
     * Creates a command log entry (so you can inspect what would have fired)
     * and always returns success. Only active in APP_ENV=local.
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

        $log = MinecraftCommandLog::create([
            'user_id' => $user?->id,
            'command' => $command,
            'command_type' => $commandType,
            'target' => $target,
            'status' => 'failed',
            'ip_address' => request()->ip(),
            'meta' => array_merge($meta, ['simulated' => true]),
            'executed_at' => now(),
        ]);

        $executionTime = (microtime(true) - $startTime) * 1000;

        $log->update([
            'status' => 'success',
            'response' => $response,
            'error_message' => null,
            'execution_time_ms' => round($executionTime),
        ]);

        return [
            'success' => true,
            'response' => $response,
            'error' => null,
        ];
    }
}
