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
     * Complete the verification process when a user enters the code in-game
     *
     * @return array ['success' => bool, 'message' => string]
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

            // Dispatch rank assignment OUTSIDE the transaction so the job sees committed data
            if ($this->completedAccount) {
                $rank = $verification->user->membership_level->minecraftRank();

                // Only set rank if user has server access (Traveler or above)
                if ($rank) {
                    SendMinecraftCommand::dispatch(
                        "lh setmember {$this->completedAccount->command_id} {$rank}",
                        'rank',
                        $this->completedAccount->command_id,
                        $verification->user,
                        ['action' => 'set_rank_on_verify', 'account_id' => $this->completedAccount->id]
                    );

                    RecordActivity::handle(
                        $verification->user,
                        'minecraft_rank_assignment_requested',
                        "Queued rank assignment '{$rank}' for {$this->completedAccount->username}"
                    );
                }

                // Sync staff position if user has one
                if ($verification->user->staff_department) {
                    SyncMinecraftStaff::run($verification->user, $verification->user->staff_department);
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
