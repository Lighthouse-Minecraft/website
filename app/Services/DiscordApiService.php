<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordApiService
{
    protected string $baseUrl = 'https://discord.com/api/v10';

    protected string $botToken;

    protected string $guildId;

    public function __construct()
    {
        $this->botToken = (string) config('services.discord.bot_token', '');
        $this->guildId = (string) config('services.discord.guild_id', '');
    }

    public function getGuildMember(string $discordUserId): ?array
    {
        $response = Http::withHeaders($this->botHeaders())
            ->get("{$this->baseUrl}/guilds/{$this->guildId}/members/{$discordUserId}");

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

        $response = Http::withHeaders($this->botHeaders())
            ->put("{$this->baseUrl}/guilds/{$this->guildId}/members/{$discordUserId}/roles/{$roleId}");

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

        $response = Http::withHeaders($this->botHeaders())
            ->delete("{$this->baseUrl}/guilds/{$this->guildId}/members/{$discordUserId}/roles/{$roleId}");

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
        $channelResponse = Http::withHeaders($this->botHeaders())
            ->post("{$this->baseUrl}/users/@me/channels", [
                'recipient_id' => $discordUserId,
            ]);

        if (! $channelResponse->successful()) {
            Log::warning('Discord createDM failed', [
                'discord_user_id' => $discordUserId,
                'status' => $channelResponse->status(),
            ]);

            return false;
        }

        $channelId = $channelResponse->json('id');

        $messageResponse = Http::withHeaders($this->botHeaders())
            ->post("{$this->baseUrl}/channels/{$channelId}/messages", [
                'content' => $content,
            ]);

        if (! $messageResponse->successful()) {
            Log::warning('Discord sendDM failed', [
                'discord_user_id' => $discordUserId,
                'channel_id' => $channelId,
                'status' => $messageResponse->status(),
            ]);
        }

        return $messageResponse->successful();
    }

    public function removeAllManagedRoles(string $discordUserId): void
    {
        $allRoleIds = array_filter(array_values(config('lighthouse.discord.roles', [])));

        foreach ($allRoleIds as $roleId) {
            $this->removeRole($discordUserId, $roleId);
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
