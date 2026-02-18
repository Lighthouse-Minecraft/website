<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class McProfileService
{
    /**
     * Get Bedrock player information including Floodgate UUID
     *
     * @return array|null ['xuid' => xuid, 'gamertag' => gamertag, 'floodgate_uuid' => uuid] or null if not found
     */
    public function getBedrockPlayerInfo(string $gamertag): ?array
    {
        try {
            $response = Http::timeout(5)->get(
                "https://mcprofile.io/api/v1/bedrock/gamertag/{$gamertag}"
            );

            if ($response->successful() && $response->json()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('MCProfile API error', [
                'gamertag' => $gamertag,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get Bedrock gamertag from Floodgate UUID
     */
    public function getBedrockGamertag(string $floodgateUuid): ?string
    {
        try {
            $response = Http::timeout(5)->get(
                "https://mcprofile.io/api/v1/bedrock/fuid/{$floodgateUuid}"
            );

            if ($response->successful() && $response->json()) {
                return $response->json()['gamertag'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('MCProfile API error', [
                'floodgate_uuid' => $floodgateUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
