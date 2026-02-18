<?php

namespace App\Actions;

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\McProfileService;
use App\Services\MinecraftRconService;
use App\Services\MojangApiService;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateVerificationCode
{
    use AsAction;

    /**
     * Generate a verification code for a user to link their Minecraft account
     *
     * @return array ['success' => bool, 'code' => string|null, 'expires_at' => datetime|null, 'error' => string|null]
     */
    public function handle(
        User $user,
        MinecraftAccountType $accountType,
        string $username
    ): array {
        // Check if user has reached max accounts
        $maxAccounts = config('lighthouse.max_minecraft_accounts');
        if ($user->minecraftAccounts()->count() >= $maxAccounts) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => "You have reached the maximum of {$maxAccounts} linked Minecraft accounts.",
            ];
        }

        // Check rate limiting
        $rateLimit = config('lighthouse.minecraft_verification_rate_limit_per_hour');
        $recentVerifications = MinecraftVerification::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentVerifications >= $rateLimit) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'You have exceeded the verification rate limit. Please try again later.',
            ];
        }

        // Verify username and get UUID via appropriate API
        $uuid = null;
        $verifiedUsername = null;

        if ($accountType === MinecraftAccountType::Java) {
            $mojangService = app(MojangApiService::class);
            $playerData = $mojangService->getJavaPlayerUuid($username);

            if (! $playerData) {
                return [
                    'success' => false,
                    'code' => null,
                    'expires_at' => null,
                    'error' => 'Unable to verify Minecraft username. Please check the spelling and try again.',
                ];
            }

            $uuid = $playerData['id'];
            $verifiedUsername = $playerData['name'];
        } else {
            // Bedrock
            $mcProfileService = app(McProfileService::class);
            $playerData = $mcProfileService->getBedrockPlayerInfo($username);

            if (! $playerData) {
                return [
                    'success' => false,
                    'code' => null,
                    'expires_at' => null,
                    'error' => 'Unable to verify Bedrock account. Please check the gamertag and try again later.',
                ];
            }

            $uuid = $playerData['floodgate_uuid'] ?? null;
            $verifiedUsername = $playerData['gamertag'] ?? $username;

            if (! $uuid) {
                return [
                    'success' => false,
                    'code' => null,
                    'expires_at' => null,
                    'error' => 'Unable to get Floodgate UUID for Bedrock account.',
                ];
            }
        }

        // Normalize UUID for comparison (remove dashes if present)
        $normalizedUuid = str_replace('-', '', $uuid);

        // Check if UUID is already linked to the current user
        $userExistingAccount = \App\Models\MinecraftAccount::where('user_id', $user->id)
            ->whereRaw("REPLACE(uuid, '-', '') = ?", [$normalizedUuid])
            ->first();

        if ($userExistingAccount) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'You have already verified this Minecraft account.',
            ];
        }

        // Check if UUID is already linked to another user
        $existingAccount = \App\Models\MinecraftAccount::whereRaw("REPLACE(uuid, '-', '') = ?", [$normalizedUuid])->first();
        if ($existingAccount && $existingAccount->user_id !== $user->id) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'This Minecraft account is already linked to another user.',
            ];
        }

        // Generate unique 6-character code (excluding confusing characters: 0, O, 1, I, l, 5, S)
        do {
            $code = strtoupper(Str::random(6));
            // Replace confusing characters
            $code = str_replace(['0', 'O', '1', 'I', 'L', '5', 'S'], ['2', '3', '4', '6', '7', '8', '9'], $code);
        } while (MinecraftVerification::where('code', $code)->exists());

        // Calculate expiry time based on grace period
        $gracePeriodMinutes = config('lighthouse.minecraft_verification_grace_period_minutes');
        $expiresAt = now()->addMinutes($gracePeriodMinutes);

        // Send whitelist add command synchronously
        $rconService = app(MinecraftRconService::class);
        $whitelistResult = $rconService->executeCommand(
            "whitelist add {$verifiedUsername}",
            'whitelist',
            $verifiedUsername,
            $user,
            ['action' => 'temp_verification', 'code' => $code]
        );

        if (! $whitelistResult['success']) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'Server is currently offline. Please try again later.',
            ];
        }

        // Create verification record
        $verification = MinecraftVerification::create([
            'user_id' => $user->id,
            'code' => $code,
            'account_type' => $accountType,
            'minecraft_username' => $verifiedUsername,
            'minecraft_uuid' => $uuid,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'whitelisted_at' => now(),
        ]);

        // Record activity
        RecordActivity::handle(
            $user,
            'minecraft_verification_generated',
            "Generated verification code for {$accountType->label()} account: {$verifiedUsername}"
        );

        return [
            'success' => true,
            'code' => $code,
            'expires_at' => $expiresAt,
            'error' => null,
        ];
    }
}
