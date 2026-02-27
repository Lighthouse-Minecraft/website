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
     * Generate a verification code and prepare a temporary whitelist entry to link a Minecraft account to a user.
     *
     * @param  User  $user  The user requesting the verification.
     * @param  MinecraftAccountType  $accountType  The Minecraft account type to verify (Java or Bedrock).
     * @param  string  $username  The provided Minecraft username or gamertag to verify.
     * @return array{success:bool, code:string|null, expires_at:\Illuminate\Support\Carbon|null, error:string|null}
     *                                                                                                              An associative array:
     *                                                                                                              - `success`: `true` on successful generation and whitelist addition, `false` on failure.
     *                                                                                                              - `code`: the generated 6-character verification code when `success` is `true`, otherwise `null`.
     *                                                                                                              - `expires_at`: timestamp when the verification code expires when `success` is `true`, otherwise `null`.
     *                                                                                                              - `error`: user-facing error message when `success` is `false`, otherwise `null`.
     */
    public function handle(
        User $user,
        MinecraftAccountType $accountType,
        string $username
    ): array {
        // Check if user is in the brig
        if ($user->isInBrig()) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'You cannot add Minecraft accounts while in the brig.',
            ];
        }

        // Check if user has reached max accounts (only Active, Verifying, Banned count)
        $maxAccounts = config('lighthouse.max_minecraft_accounts');
        if ($user->minecraftAccounts()->countingTowardLimit()->count() >= $maxAccounts) {
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
            $lookupUsername = ltrim($username, '.');
            $playerData = $mcProfileService->getBedrockPlayerInfo($lookupUsername);

            if (! $playerData) {
                return [
                    'success' => false,
                    'code' => null,
                    'expires_at' => null,
                    'error' => 'Unable to verify Bedrock account. Please check the gamertag and try again later.',
                ];
            }

            $uuid = $playerData['floodgate_uuid'] ?? null;
            $verifiedUsername = $playerData['gamertag'] ?? $lookupUsername;

            // Bedrock usernames appear with a leading dot on Floodgate servers.
            if (! str_starts_with($verifiedUsername, '.')) {
                $verifiedUsername = '.'.$verifiedUsername;
            }

            if (! $uuid) {
                return [
                    'success' => false,
                    'code' => null,
                    'expires_at' => null,
                    'error' => 'Unable to get Floodgate UUID for Bedrock account.',
                ];
            }
        }

        // Check if UUID is already linked (any status — including verifying/cancelled blocks re-use)
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

        // Validate API-returned values before persisting (defense-in-depth)
        $usernamePattern = $accountType === MinecraftAccountType::Bedrock
            ? '/^\.?[A-Za-z0-9_ ]{1,16}$/'
            : '/^[A-Za-z0-9_]{3,16}$/';

        if (! preg_match($usernamePattern, $verifiedUsername)) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'The API returned an invalid username format. Please try again.',
            ];
        }

        $cleanUuid = str_replace('-', '', $uuid);
        if (! preg_match('/^[0-9a-fA-F]{32}$/', $cleanUuid)) {
            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'The API returned an invalid UUID format. Please try again.',
            ];
        }

        // Create the MinecraftAccount record in 'verifying' state before sending whitelist
        $normalizedUuid = str_replace('-', '', $uuid);
        try {
            $account = MinecraftAccount::create([
                'user_id' => $user->id,
                'username' => $verifiedUsername,
                'uuid' => $uuid,
                'avatar_url' => 'https://mc-heads.net/avatar/'.$normalizedUuid,
                'account_type' => $accountType,
                'status' => 'verifying',
                'last_username_check_at' => now(),
                // verified_at intentionally null until CompleteVerification promotes to active
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create MinecraftAccount', [
                'user_id' => $user->id,
                'username' => $verifiedUsername,
                'uuid' => $uuid,
                'account_type' => $accountType->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'An error occurred. Please try again later.',
            ];
        }

        // Send whitelist add command synchronously using the correct command for account type
        $rconService = app(MinecraftRconService::class);
        $whitelistResult = $rconService->executeCommand(
            $account->whitelistAddCommand(),
            'whitelist',
            $verifiedUsername,
            $user,
            ['action' => 'temp_verification', 'code' => $code]
        );

        if (! $whitelistResult['success']) {
            $account->delete();

            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'Server is currently offline. Please try again later.',
            ];
        }

        // Create verification record; roll back account + whitelist on failure
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
            \Log::error('Failed to create MinecraftVerification', [
                'user_id' => $user->id,
                'code' => $code,
                'username' => $verifiedUsername,
                'uuid' => $uuid,
                'account_type' => $accountType->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $rconService->executeCommand(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $verifiedUsername,
                $user,
                ['action' => 'cleanup_failed_verification']
            );
            $account->delete();

            return [
                'success' => false,
                'code' => null,
                'expires_at' => null,
                'error' => 'An error occurred. Please try again later.',
            ];
        }

        // Record activity — both logs written only after verification record is committed
        RecordActivity::handle(
            $user,
            'minecraft_whitelisted',
            "Added {$verifiedUsername} to server whitelist"
        );

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
