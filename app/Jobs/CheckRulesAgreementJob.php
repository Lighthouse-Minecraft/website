<?php

namespace App\Jobs;

use App\Actions\PutUserInRulesBrig;
use App\Actions\SendRulesAgreementReminder;
use App\Enums\BrigType;
use App\Enums\MembershipLevel;
use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckRulesAgreementJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $version = RuleVersion::currentPublished();

        if (! $version || ! $version->published_at) {
            return;
        }

        $agreedUserIds = $version->agreements()->pluck('user_id');

        $unagreedUsers = User::whereNotIn('id', $agreedUserIds)
            ->where('membership_level', '>=', MembershipLevel::Stowaway->value)
            ->get();

        $daysSincePublish = (int) $version->published_at->diffInDays(now());

        foreach ($unagreedUsers as $user) {
            if ($daysSincePublish >= 28) {
                if (! $user->isInBrig() || $user->brig_type !== BrigType::RulesNonCompliance) {
                    PutUserInRulesBrig::run($user);
                }
            } elseif ($daysSincePublish >= 14 && $user->rules_reminder_sent_at === null) {
                SendRulesAgreementReminder::run($user, $version);
            }
        }
    }
}
