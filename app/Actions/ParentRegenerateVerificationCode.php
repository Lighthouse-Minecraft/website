<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class ParentRegenerateVerificationCode
{
    use AsAction;

    /**
     * Restart verification for a child's Cancelled or Cancelling MinecraftAccount.
     *
     * Validates the parent-child relationship, checks that the child is not in the brig
     * and that Minecraft access is enabled. Rate-limits against the child's user ID.
     *
     * @return array{success:bool, code:string|null, expires_at:\Illuminate\Support\Carbon|null, error:string|null}
     */
    public function handle(MinecraftAccount $account, User $parent): array
    {
        $child = $account->user;

        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'You do not have permission to manage this account.'];
        }

        if (! in_array($account->status, [MinecraftAccountStatus::Cancelled, MinecraftAccountStatus::Cancelling])) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Only cancelled or cancelling accounts can be retried.'];
        }

        if ($child->isInBrig()) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Cannot restart verification while the child is in the brig.'];
        }

        if (! $child->parent_allows_minecraft) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Minecraft access is currently disabled for this child.'];
        }

        // Rate limiting against child's user ID
        $rateLimit = config('lighthouse.minecraft_verification_rate_limit_per_hour');
        $recentVerifications = MinecraftVerification::where('user_id', $child->id)
            ->where('created_at', '>=', now()->subHour())
            ->pending()
            ->count();

        if ($recentVerifications >= $rateLimit) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Verification rate limit reached for this child. Please try again later.'];
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
            $parent,
            ['action' => 'parent_retry_verification', 'account_id' => $account->id, 'child_user_id' => $child->id]
        );

        if (! $whitelistResult['success']) {
            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'Server is currently offline. Please try again later.'];
        }

        $originalStatus = $account->status;

        // Set account back to Verifying and create verification record inside try so
        // rollback covers both mutations if either fails.
        try {
            $account->update(['status' => MinecraftAccountStatus::Verifying]);

            MinecraftVerification::create([
                'user_id' => $child->id,
                'code' => $code,
                'account_type' => $account->account_type,
                'minecraft_username' => $account->username,
                'minecraft_uuid' => $account->uuid,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'whitelisted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to create MinecraftVerification on parent retry', [
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            // Rollback: remove whitelist and revert to original status
            $rconService->executeCommand(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $account->username,
                $parent,
                ['action' => 'cleanup_failed_parent_retry']
            );
            $account->update(['status' => $originalStatus]);

            return ['success' => false, 'code' => null, 'expires_at' => null, 'error' => 'An error occurred. Please try again later.'];
        }

        RecordActivity::run(
            $child,
            'minecraft_verification_regenerated',
            "{$parent->name} restarted verification for {$account->account_type->label()} account: {$account->username}"
        );

        return [
            'success' => true,
            'code' => $code,
            'expires_at' => $expiresAt,
            'error' => null,
        ];
    }
}
