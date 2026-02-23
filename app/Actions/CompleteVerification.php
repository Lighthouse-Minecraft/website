<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteVerification
{
    use AsAction;

    private ?MinecraftAccount $completedAccount = null;

    /**
     * Completes a pending Minecraft verification and links the Minecraft account to the user.
     *
     * Validates the provided verification code, matches the username (case-insensitive) and UUID (dashes ignored),
     * and on success promotes the account to active, records activity, and triggers optional post-link actions.
     *
     * @param  string  $code  The in-game verification code submitted by the player.
     * @param  string  $username  The Minecraft username reported by the server (compared case-insensitively).
     * @param  string  $uuid  The Minecraft UUID reported by the server; dashes are ignored during comparison.
     * @return array An array with keys:
     *               - 'success' => bool: `true` if the account was linked, `false` otherwise.
     *               - 'message' => string: Human-readable outcome or error message.
     */
    public function handle(
        string $code,
        string $username,
        string $uuid
    ): array {
        // Normalize UUID (remove dashes for comparison)
        $normalizedUuid = str_replace('-', '', $uuid);

        // Find pending verification by code
        $verification = MinecraftVerification::where('code', $code)
            ->pending()
            ->first();

        if (! $verification) {
            return [
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ];
        }

        // Check if expired
        if ($verification->expires_at < now()) {
            $verification->update(['status' => 'expired']);

            return [
                'success' => false,
                'message' => 'Verification code has expired.',
            ];
        }

        // Normalize stored UUID for comparison
        $storedUuid = str_replace('-', '', $verification->minecraft_uuid);

        // Verify the username and UUID match (case-insensitive username, normalized UUID)
        if (strcasecmp($verification->minecraft_username, $username) !== 0 || $storedUuid !== $normalizedUuid) {
            return [
                'success' => false,
                'message' => 'Username or UUID mismatch.',
            ];
        }

        try {
            DB::transaction(function () use ($verification, $uuid) {
                // Find the verifying MinecraftAccount created by GenerateVerificationCode
                $account = MinecraftAccount::whereNormalizedUuid($uuid)
                    ->lockForUpdate()
                    ->first();

                if (! $account) {
                    throw new \DomainException('Account record not found. Please generate a new verification code.');
                }

                if ($account->user_id !== $verification->user_id) {
                    throw new \DomainException('This Minecraft account is already linked to another user.');
                }

                if ($account->status !== MinecraftAccountStatus::Verifying) {
                    throw new \DomainException('Account is not in a verifying state.');
                }

                // Promote to active
                $account->update([
                    'status' => MinecraftAccountStatus::Active,
                    'verified_at' => now(),
                    'last_username_check_at' => now(),
                ]);

                // Mark verification as completed
                $verification->update(['status' => 'completed']);

                // Record activity
                RecordActivity::handle(
                    $verification->user,
                    'minecraft_account_linked',
                    "Linked {$verification->account_type->label()} account: {$verification->minecraft_username}"
                );

                $this->completedAccount = $account;
            });

            // Sync permissions OUTSIDE the transaction so the jobs see committed data.
            // Only sync this specific account — not all of the user's accounts.
            if ($this->completedAccount) {
                $user = $verification->user;
                $account = $this->completedAccount;

                $rank = $user->membership_level->minecraftRank();
                if ($rank !== null) {
                    SendMinecraftCommand::dispatch(
                        "lh setmember {$account->username} {$rank}",
                        'rank',
                        $account->username,
                        $user,
                        ['action' => 'sync_rank', 'membership_level' => $user->membership_level->value]
                    );

                    RecordActivity::handle(
                        $user,
                        'minecraft_rank_synced',
                        "Synced Minecraft rank to {$rank} for {$account->username}"
                    );
                }

                if ($user->staff_department !== null) {
                    SendMinecraftCommand::dispatch(
                        "lh setstaff {$account->username} {$user->staff_department->value}",
                        'rank',
                        $account->username,
                        $user,
                        ['action' => 'set_staff_position', 'department' => $user->staff_department->value]
                    );

                    RecordActivity::handle(
                        $user,
                        'minecraft_staff_position_set',
                        "Set Minecraft staff position to {$user->staff_department->label()} for {$account->username}"
                    );
                }
            }

            return [
                'success' => true,
                'message' => 'Minecraft account successfully linked!',
            ];
        } catch (\DomainException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Minecraft verification completion failed', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while linking your account. Please try again.',
            ];
        }
    }
}
