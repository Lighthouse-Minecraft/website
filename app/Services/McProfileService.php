<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McProfileService
{
    /**
     * Get Bedrock player information including Floodgate UUID.
     *
     * Tries GeyserMC Global API first (most reliable), falls back to mcprofile.io.
     *
     * @return array|null ['xuid' => xuid, 'gamertag' => gamertag, 'floodgate_uuid' => uuid] or null if not found
     */
    public function getBedrockPlayerInfo(string $gamertag): ?array
    {
        return $this->getBedrockPlayerInfoFromGeyser($gamertag)
            ?? $this->getBedrockPlayerInfoFromMcProfile($gamertag);
    }

    /**
     * Look up Bedrock player info via GeyserMC Global API.
     *
     * Uses the deterministic Floodgate UUID formula: UUID(0, xuid_as_long)
     * → 00000000-0000-0000-{high4_hex}-{low12_hex}
     */
    private function getBedrockPlayerInfoFromGeyser(string $gamertag): ?array
    {
        try {
            $response = Http::timeout(5)->get(
                'https://api.geysermc.org/v2/xbox/xuid/'.urlencode($gamertag)
            );

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $xuid = $data['xuid'] ?? null;

            if (! $xuid) {
                return null;
            }

            $floodgateUuid = $this->xuidToFloodgateUuid((string) $xuid);

            return [
                'xuid' => (string) $xuid,
                'gamertag' => $gamertag,
                'floodgate_uuid' => $floodgateUuid,
            ];
        } catch (\Exception $e) {
            Log::error('GeyserMC API error', [
                'gamertag' => $gamertag,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Look up Bedrock player info via mcprofile.io (fallback).
     */
    private function getBedrockPlayerInfoFromMcProfile(string $gamertag): ?array
    {
        try {
            $response = Http::timeout(5)->get(
                'https://mcprofile.io/api/v1/bedrock/gamertag/'.urlencode($gamertag)
            );

            if ($response->successful() && $response->json()) {
                $data = $response->json();

                // Ensure floodgate_uuid is present — mcprofile.io sometimes omits it
                if (empty($data['floodgate_uuid']) && ! empty($data['xuid'])) {
                    $data['floodgate_uuid'] = $this->xuidToFloodgateUuid((string) $data['xuid']);
                }

                return $data;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('MCProfile API error', [
                'gamertag' => $gamertag,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Compute Floodgate UUID from Xbox XUID using GeyserMC's deterministic formula.
     *
     * Floodgate generates UUIDs as: new UUID(0, xuid_as_long)
     * Resulting in: 00000000-0000-0000-{high 4 hex chars}-{low 12 hex chars}
     */
    public function xuidToFloodgateUuid(string $xuid): string
    {
        $hex = str_pad(dechex((int) $xuid), 16, '0', STR_PAD_LEFT);

        return sprintf(
            '%s-%s-%s-%s-%s',
            '00000000',
            '0000',
            '0000',
            substr($hex, 0, 4),
            substr($hex, 4)
        );
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
            Log::error('MCProfile API error', [
                'floodgate_uuid' => $floodgateUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
