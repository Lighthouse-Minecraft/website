<?php

namespace App\Actions;

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\McProfileService;
use App\Services\MinecraftRconService;
use App\Services\MojangApiService;
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
            ->pending()
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

        // Check if UUID is already linked (to this user or another)
        $existingAccount = MinecraftAccount::whereNormalizedUuid($uuid)->first();

        if ($existingAccount) {
            if ($existingAccount->user_id === $user->id) {
                return [
                    'success' => false,
                    'code' => null,
                    'expires_at' => null,
                    'error' => 'You have already verified this Minecraft account.',
                ];
            }

            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'This Minecraft account is already linked to another user.',
            ];
        }

        // Generate unique 6-character code (excluding confusing characters: 0, O, 1, I, L, 5, S)
        $allowedCharacters = '2346789ABCDEFGHJKMNPQRTUVWXYZ';
        $codeLength = 6;
        $maxAttempts = 100;
        $attempts = 0;

        do {
            $code = '';

            for ($i = 0; $i < $codeLength; $i++) {
                $index = random_int(0, strlen($allowedCharacters) - 1);
                $code .= $allowedCharacters[$index];
            }

            $attempts++;

            if ($attempts >= $maxAttempts) {
                return [
                    'success' => false,
                    'code' => null,
                    'expires_at' => null,
                    'error' => 'Unable to generate a verification code at this time. Please try again later.',
                ];
            }
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

        // Create verification record; undo whitelist on failure
        try {
            MinecraftVerification::create([
                'user_id' => $user->id,
                'code' => $code,
                'account_type' => $accountType,
                'minecraft_username' => $verifiedUsername,
                'minecraft_uuid' => $uuid,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'whitelisted_at' => now(),
            ]);
        } catch (\Exception $e) {
            $rconService->executeCommand(
                "whitelist remove {$verifiedUsername}",
                'whitelist',
                $verifiedUsername,
                $user,
                ['action' => 'cleanup_failed_verification']
            );

            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'An error occurred. Please try again later.',
            ];
        }

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
