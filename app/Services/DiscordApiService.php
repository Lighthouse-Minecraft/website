<?php

namespace App\Services;

use App\Models\DiscordApiLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordApiService
{
    protected string $baseUrl = 'https://discord.com/api/v10';

    protected string $botToken;

    protected string $guildId;

    protected int $maxRetries = 2;

    public function __construct()
    {
        $this->botToken = (string) config('services.discord.bot_token', '');
        $this->guildId = (string) config('services.discord.guild_id', '');
    }

    public function getGuildMember(string $discordUserId): ?array
    {
        $response = $this->requestWithRetry('GET', "/guilds/{$this->guildId}/members/{$discordUserId}", actionType: 'get_member', target: $discordUserId);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    public function addRole(string $discordUserId, string $roleId): bool
    {
        if (empty($roleId)) {
            return false;
        }

        $response = $this->requestWithRetry('PUT', "/guilds/{$this->guildId}/members/{$discordUserId}/roles/{$roleId}", actionType: 'add_role', target: $discordUserId, meta: ['role_id' => $roleId]);

        if (! $response->successful()) {
            Log::warning('Discord addRole failed', [
                'discord_user_id' => $discordUserId,
                'role_id' => $roleId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->successful();
    }

    public function removeRole(string $discordUserId, string $roleId): bool
    {
        if (empty($roleId)) {
            return false;
        }

        $response = $this->requestWithRetry('DELETE', "/guilds/{$this->guildId}/members/{$discordUserId}/roles/{$roleId}", actionType: 'remove_role', target: $discordUserId, meta: ['role_id' => $roleId]);

        if (! $response->successful()) {
            Log::warning('Discord removeRole failed', [
                'discord_user_id' => $discordUserId,
                'role_id' => $roleId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->successful();
    }

    public function sendDirectMessage(string $discordUserId, string $content): bool
    {
        $channelResponse = $this->requestWithRetry('POST', '/users/@me/channels', [
            'recipient_id' => $discordUserId,
        ], actionType: 'create_dm', target: $discordUserId);

        if (! $channelResponse->successful()) {
            Log::warning('Discord createDM failed', [
                'discord_user_id' => $discordUserId,
                'status' => $channelResponse->status(),
            ]);

            return false;
        }

        $channelId = $channelResponse->json('id');

        $messageResponse = $this->requestWithRetry('POST', "/channels/{$channelId}/messages", [
            'content' => $content,
        ], actionType: 'send_dm', target: $discordUserId);

        if (! $messageResponse->successful()) {
            Log::warning('Discord sendDM failed', [
                'discord_user_id' => $discordUserId,
                'channel_id' => $channelId,
                'status' => $messageResponse->status(),
            ]);
        }

        return $messageResponse->successful();
    }

    /**
     * Sync a subset of managed roles for a guild member.
     *
     * Fetches the member's current roles, diffs against the desired set within the
     * managed role IDs, and only adds/removes what's actually changed.
     *
     * @param  string  $discordUserId  The Discord user ID.
     * @param  array<string>  $managedRoleIds  All role IDs this operation manages (e.g. all membership roles).
     * @param  array<string>  $desiredRoleIds  The role IDs the user should have from the managed set.
     * @return bool Whether the member was found in the guild.
     */
    public function syncManagedRoles(string $discordUserId, array $managedRoleIds, array $desiredRoleIds): bool
    {
        $member = $this->getGuildMember($discordUserId);
        if (! $member) {
            return false;
        }

        $currentRoles = $member['roles'] ?? [];
        $managedRoleIds = array_filter($managedRoleIds);
        $desiredRoleIds = array_intersect(array_filter($desiredRoleIds), $managedRoleIds);

        // Only look at managed roles the user currently has
        $managedCurrent = array_intersect($currentRoles, $managedRoleIds);

        // Roles to remove: managed roles the user has but shouldn't
        $toRemove = array_diff($managedCurrent, $desiredRoleIds);

        // Roles to add: desired roles the user doesn't have yet
        $toAdd = array_diff($desiredRoleIds, $currentRoles);

        foreach ($toRemove as $roleId) {
            $this->removeRole($discordUserId, $roleId);
        }

        foreach ($toAdd as $roleId) {
            $this->addRole($discordUserId, $roleId);
        }

        return true;
    }

    public function sendChannelMessage(string $channelId, string $content): bool
    {
        $response = $this->requestWithRetry('POST', "/channels/{$channelId}/messages", [
            'content' => $content,
        ], actionType: 'send_channel_message', target: $channelId);

        if (! $response->successful()) {
            Log::warning('Discord sendChannelMessage failed', [
                'channel_id' => $channelId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->successful();
    }

    public function removeAllManagedRoles(string $discordUserId): void
    {
        $allRoleIds = array_filter(array_values(config('lighthouse.discord.roles', [])));

        if (empty($allRoleIds)) {
            return;
        }

        foreach ($allRoleIds as $roleId) {
            $this->removeRole($discordUserId, $roleId);
        }
    }

    /**
     * Make a Discord API request with automatic rate limit retry and logging.
     *
     * If Discord returns a 429, sleeps for the retry_after duration
     * and retries up to $maxRetries times.
     */
    protected function requestWithRetry(string $method, string $path, array $data = [], string $actionType = 'unknown', ?string $target = null, ?array $meta = null): Response
    {
        $url = "{$this->baseUrl}{$path}";
        $startTime = microtime(true);

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            $pending = Http::withHeaders($this->botHeaders())->timeout(5);

            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->post($url, $data),
                'PUT' => $pending->put($url, $data),
                'DELETE' => $pending->delete($url),
            };

            if ($response->status() !== 429) {
                $this->logApiCall($method, $path, $actionType, $target, $response, $startTime, $meta);

                return $response;
            }

            $retryAfter = $response->json('retry_after', 1);

            Log::info('Discord rate limited, waiting to retry', [
                'path' => $path,
                'retry_after' => $retryAfter,
                'attempt' => $attempt + 1,
            ]);

            usleep((int) ($retryAfter * 1_000_000));
        }

        $this->logApiCall($method, $path, $actionType, $target, $response, $startTime, $meta);

        return $response;
    }

    protected function logApiCall(string $method, string $path, string $actionType, ?string $target, Response $response, float $startTime, ?array $meta): void
    {
        $executionTime = round((microtime(true) - $startTime) * 1000);

        try {
            DiscordApiLog::create([
                'user_id' => auth()->id(),
                'method' => strtoupper($method),
                'endpoint' => $path,
                'action_type' => $actionType,
                'target' => $target,
                'status' => $response->successful() ? 'success' : 'failed',
                'http_status' => $response->status(),
                'response' => $response->successful() ? null : $response->body(),
                'error_message' => $response->successful() ? null : $response->body(),
                'meta' => $meta,
                'executed_at' => now(),
                'execution_time_ms' => (int) $executionTime,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log Discord API call', ['error' => $e->getMessage()]);
        }
    }

    protected function botHeaders(): array
    {
        return [
            'Authorization' => "Bot {$this->botToken}",
            'Content-Type' => 'application/json',
        ];
    }
}
