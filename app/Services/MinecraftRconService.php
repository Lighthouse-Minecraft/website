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
            ['connected' => $connected, 'result' => $result] = $this->connectAndSend($command);

            if (! $connected) {
                $errorMessage = 'Failed to connect to RCON server';
            } elseif ($result === false) {
                $response = null;
                $errorMessage = 'Failed to send command to RCON server';
            } else {
                $response = $result;
                if ($this->isLhCommand($command)) {
                    if (str_starts_with(trim($response), 'Success:')) {
                        $status = 'success';
                    } else {
                        $errorMessage = 'lh command returned non-success response: '.(empty($response) ? '(empty)' : $response);
                    }
                } else {
                    $status = 'success';
                }
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

    /**
     * Open an RCON connection and send a command.
     *
     * Returns ['connected' => true, 'result' => string|false] on successful connection,
     * or ['connected' => false, 'result' => null] when the connection itself fails.
     * Extracted as a protected method so tests can override it with simulated responses.
     */
    protected function connectAndSend(string $command): array
    {
        $rcon = new Rcon(
            config('services.minecraft.rcon_host'),
            config('services.minecraft.rcon_port'),
            config('services.minecraft.rcon_password'),
            3 // timeout in seconds
        );

        if (! $rcon->connect()) {
            return ['connected' => false, 'result' => null];
        }

        return ['connected' => true, 'result' => $rcon->sendCommand($command)];
    }

    private function isLhCommand(string $command): bool
    {
        return str_starts_with($command, 'lh ');
    }
}
