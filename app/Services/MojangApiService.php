<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MojangApiService
{
    /**
     * Get Java Edition player UUID from username
     *
     * @return array|null ['id' => uuid, 'name' => username] or null if not found
     */
    public function getJavaPlayerUuid(string $username): ?array
    {
        try {
            $response = Http::timeout(5)->get(
                "https://api.mojang.com/users/profiles/minecraft/{$username}"
            );

            if ($response->successful() && $response->json()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Mojang API error', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get Java Edition username from UUID
     */
    public function getJavaUsername(string $uuid): ?string
    {
        try {
            $response = Http::timeout(5)->get(
                "https://sessionserver.mojang.com/session/minecraft/profile/{$uuid}"
            );

            if ($response->successful() && $response->json()) {
                return $response->json()['name'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Mojang API error', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
