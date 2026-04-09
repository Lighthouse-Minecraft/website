<?php

namespace App\Actions;

use App\Enums\BrigType;
use App\Enums\MembershipLevel;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\UserRuleAgreement;
use Lorisleiva\Actions\Concerns\AsAction;

class AgreeToRulesVersion
{
    use AsAction;

    /**
     * Record agreement to the current published rules version.
     *
     * Promotes Drifter-level users to Stowaway and auto-lifts any
     * rules_non_compliance brig. Idempotent — safe to call multiple times.
     */
    public function handle(User $user, User $actingUser): void
    {
        $version = RuleVersion::currentPublished();

        if (! $version) {
            return;
        }

        UserRuleAgreement::updateOrCreate(
            ['user_id' => $user->id, 'rule_version_id' => $version->id],
            ['agreed_at' => now()],
        );

        $user->rules_accepted_at = now();
        $user->rules_accepted_by_user_id = $actingUser->id;
        $user->save();

        $isSelf = $user->id === $actingUser->id;

        if ($user->isLevel(MembershipLevel::Drifter)) {
            $description = $isSelf
                ? 'User accepted community rules and was promoted to Stowaway.'
                : "Community rules agreed on behalf of user by {$actingUser->name} (parent). User promoted to Stowaway.";

            RecordActivity::run($user, 'rules_accepted', $description, $actingUser);
            PromoteUser::run($user, MembershipLevel::Stowaway);
        } else {
            RecordActivity::run($user, 'rules_accepted', "User agreed to rules version {$version->version_number}.", $actingUser);
        }

        if ($user->isInBrig() && $user->brig_type === BrigType::RulesNonCompliance) {
            ReleaseUserFromBrig::run($user, $user, 'Rules non-compliance brig lifted after agreeing to rules.', notify: false);
        }
    }
}
