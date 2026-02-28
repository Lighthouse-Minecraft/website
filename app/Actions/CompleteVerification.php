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
        string $uuid,
        ?string $bedrockUsername = null,
        ?string $bedrockXuid = null,
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

        // Standard match: case-insensitive username + normalized UUID
        $matched = strcasecmp($verification->minecraft_username, $username) === 0
            && $storedUuid === $normalizedUuid;

        // Fallback 1: Strip single-character Floodgate prefix from the INCOMING username.
        // Handles: stored "Ghostridr6007" vs incoming ".Ghostridr6007" (unlinked Bedrock, no dot stored).
        if (! $matched && strlen($username) > 1 && $storedUuid === $normalizedUuid) {
            $strippedIncoming = substr($username, 1);
            if (strcasecmp($verification->minecraft_username, $strippedIncoming) === 0) {
                $matched = true;
            }
        }

        // Fallback 2: If bedrock_username was provided, compare it against the stored
        // username (with and without prefix stripped). Handles linked Bedrock accounts
        // where the incoming minecraft_username is the Java identity.
        $usedBedrockFallback = false;
        if (! $matched && $bedrockUsername !== null) {
            $storedUsername = $verification->minecraft_username;

            // Direct match: stored "Ghostridr6007" == bedrock_username "Ghostridr6007"
            if (strcasecmp($storedUsername, $bedrockUsername) === 0) {
                $matched = true;
                $usedBedrockFallback = true;
            }
            // Strip stored prefix: stored ".Ghostridr6007" -> "Ghostridr6007" == "Ghostridr6007"
            elseif (strlen($storedUsername) > 1) {
                $strippedStoredUsername = substr($storedUsername, 1);
                if (strcasecmp($strippedStoredUsername, $bedrockUsername) === 0) {
                    $matched = true;
                    $usedBedrockFallback = true;
                }
            }
        }

        if (! $matched) {
            return [
                'success' => false,
                'message' => 'Username or UUID mismatch.',
            ];
        }

        // When bedrock fallback matched, look up the account by the verification's
        // stored UUID (the Floodgate UUID) instead of the incoming Java linked UUID.
        $accountLookupUuid = $usedBedrockFallback ? $verification->minecraft_uuid : $uuid;

        try {
            DB::transaction(function () use ($verification, $accountLookupUuid, $bedrockXuid) {
                // Find the verifying MinecraftAccount created by GenerateVerificationCode
                $account = MinecraftAccount::whereNormalizedUuid($accountLookupUuid)
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
                $updateData = [
                    'status' => MinecraftAccountStatus::Active,
                    'verified_at' => now(),
                    'last_username_check_at' => now(),
                ];

                // Store the bedrock XUID if provided and not already set
                if ($bedrockXuid !== null && $account->bedrock_xuid === null) {
                    $updateData['bedrock_xuid'] = $bedrockXuid;
                }

                $account->update($updateData);

                // Mark verification as completed
                $verification->update(['status' => 'completed']);

                // If user has no primary account yet, make this one primary
                $hasPrimary = MinecraftAccount::where('user_id', $account->user_id)
                    ->where('is_primary', true)
                    ->active()
                    ->where('id', '!=', $account->id)
                    ->exists();

                if (! $hasPrimary) {
                    $account->update(['is_primary' => true]);
                }

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

                // Grant new-player reward (first verified account only).
                // Wrapped in try/catch so reward failures never block a successful verification.
                try {
                    GrantNewPlayerReward::run($account, $user);
                } catch (\Throwable $e) {
                    Log::warning('New-player reward failed after successful verification', [
                        'account_id' => $account->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
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
