<?php

namespace App\Actions;

use App\Enums\MembershipLevel;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AgreeToRules
{
    use AsAction;

    /**
     * Record rules agreement for $user, performed by $actingUser.
     *
     * @return array{success: bool, message: string}
     */
    public function handle(User $user, User $actingUser): array
    {
        if ($user->isAtLeastLevel(MembershipLevel::Stowaway)) {
            return ['success' => false, 'message' => 'User has already agreed to the rules.'];
        }

        $user->rules_accepted_at = now();
        $user->rules_accepted_by_user_id = $actingUser->id;
        $user->save();

        $isSelf = $user->id === $actingUser->id;

        $description = $isSelf
            ? 'User accepted community rules and was promoted to Stowaway.'
            : "Community rules agreed on behalf of user by {$actingUser->name} (parent). User promoted to Stowaway.";

        RecordActivity::run($user, 'rules_accepted', $description, $actingUser);

        PromoteUser::run($user, MembershipLevel::Stowaway);

        return ['success' => true, 'message' => 'Rules accepted. User promoted to Stowaway.'];
    }
}
