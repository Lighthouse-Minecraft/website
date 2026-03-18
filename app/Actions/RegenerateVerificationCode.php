<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class RegenerateVerificationCode
{
    use AsAction;

    /**
     * Regenerate a verification code for an existing Cancelled or Cancelling MinecraftAccount.
     *
     * Skips the API username/UUID lookup since the account already has verified data.
     * Re-whitelists the player and creates a fresh verification record.
     *
     * @return array{success:bool, code:string|null, expires_at:\Illuminate\Support\Carbon|null, error:string|null}
     */
    public function handle(MinecraftAccount $account, User $user): array
    {
        if ($account->user_id !== $user->id) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Account does not belong to you.'];
        }

        if (! in_array($account->status, [MinecraftAccountStatus::Cancelled, MinecraftAccountStatus::Cancelling])) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Only cancelled or cancelling accounts can be retried.'];
        }

        if ($user->isInBrig()) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'You cannot verify Minecraft accounts while in the brig.'];
        }

        // Rate limiting
        $rateLimit = config('lighthouse.minecraft_verification_rate_limit_per_hour');
        $recentVerifications = MinecraftVerification::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->pending()
            ->count();

        if ($recentVerifications >= $rateLimit) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'You have exceeded the verification rate limit. Please try again later.'];
        }

        // Generate unique code
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
                return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Unable to generate a verification code at this time. Please try again later.'];
            }
        } while (MinecraftVerification::where('code', $code)->exists());

        $gracePeriodMinutes = config('lighthouse.minecraft_verification_grace_period_minutes');
        $expiresAt = now()->addMinutes($gracePeriodMinutes);

        // Re-whitelist
        $rconService = app(MinecraftRconService::class);
        $whitelistResult = $rconService->executeCommand(
            $account->whitelistAddCommand(),
            'whitelist',
            $account->username,
            $user,
            ['action' => 'retry_verification', 'account_id' => $account->id]
        );

        if (! $whitelistResult['success']) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Server is currently offline. Please try again later.'];
        }

        // Set account back to Verifying
        $account->update(['status' => MinecraftAccountStatus::Verifying]);

        // Create verification record
        try {
            MinecraftVerification::create([
                'user_id' => $user->id,
                'code' => $code,
                'account_type' => $account->account_type,
                'minecraft_username' => $account->username,
                'minecraft_uuid' => $account->uuid,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'whitelisted_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create MinecraftVerification on retry', [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            // Rollback: remove whitelist and revert to cancelled
            $rconService->executeCommand(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $account->username,
                $user,
                ['action' => 'cleanup_failed_retry']
            );
            $account->update(['status' => MinecraftAccountStatus::Cancelled]);

            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'An error occurred. Please try again later.'];
        }

        RecordActivity::run(
            $user,
            'minecraft_verification_regenerated',
            "Regenerated verification code for {$account->account_type->label()} account: {$account->username}"
        );

        return [
            'success' => true,
            'code' => $code,
            'expires_at' => $expiresAt,
            'error' => null,
        ];
    }
}
