<?php

namespace App\Actions;

use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteVerification
{
    use AsAction;

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
            ->where('status', 'pending')
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

        // Check if UUID is already linked to another user (normalize for comparison)
        $existingAccount = MinecraftAccount::whereRaw("REPLACE(uuid, '-', '') = ?", [$normalizedUuid])->first();
        if ($existingAccount && $existingAccount->user_id !== $verification->user_id) {
            return [
                'success' => false,
                'message' => 'This Minecraft account is already linked to another user.',
            ];
        }

        try {
            DB::transaction(function () use ($verification) {
                // Create permanent MinecraftAccount record
                MinecraftAccount::create([
                    'user_id' => $verification->user_id,
                    'username' => $verification->minecraft_username,
                    'uuid' => $verification->minecraft_uuid,
                    'account_type' => $verification->account_type,
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
            });

            return [
                'success' => true,
                'message' => 'Minecraft account successfully linked!',
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
