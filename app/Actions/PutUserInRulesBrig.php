<?php

namespace App\Actions;

use App\Enums\BrigType;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class PutUserInRulesBrig
{
    use AsAction;

    /**
     * Place a user in a rules_non_compliance brig with no expiry.
     *
     * Skips silently if the user is already in a rules_non_compliance brig.
     * The brig is auto-lifted when the user agrees to the current rules via AgreeToRulesVersion.
     */
    public function handle(User $user): void
    {
        if ($user->isInBrig() && $user->brig_type === BrigType::RulesNonCompliance) {
            return;
        }

        if ($user->isInBrig()) {
            return;
        }

        $admin = User::where('email', 'system@lighthouse.local')->first() ?? $user;

        PutUserInBrig::run(
            target: $user,
            admin: $admin,
            reason: 'Failed to agree to the updated community rules within the required timeframe.',
            expiresAt: null,
            appealAvailableAt: null,
            brigType: BrigType::RulesNonCompliance,
            notify: true,
        );
    }
}
