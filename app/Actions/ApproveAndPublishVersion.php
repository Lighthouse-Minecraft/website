<?php

namespace App\Actions;

use App\Enums\MembershipLevel;
use App\Models\Rule;
use App\Models\RuleVersion;
use App\Models\User;
use App\Notifications\RulesVersionPublishedNotification;
use App\Services\TicketNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ApproveAndPublishVersion
{
    use AsAction;

    /**
     * Approve and publish a submitted rule version.
     *
     * Validates that the approver is not the same as the creator, transitions the version
     * to published, activates draft rules, deactivates rules marked for removal, and
     * notifies all active users that the rules have been updated.
     */
    public function handle(RuleVersion $version, User $approvedBy): void
    {
        if ($version->status !== 'submitted') {
            throw new AuthorizationException('Only submitted versions can be approved.');
        }

        if ($version->created_by_user_id === $approvedBy->id) {
            throw new AuthorizationException('The creator of a draft cannot approve their own version.');
        }

        $version->status = 'published';
        $version->approved_by_user_id = $approvedBy->id;
        $version->published_at = now();
        $version->save();

        // Activate draft rules in the version (deactivate_on_publish = false)
        $version->rules()
            ->wherePivot('deactivate_on_publish', false)
            ->where('rules.status', 'draft')
            ->each(fn (Rule $rule) => $rule->update(['status' => 'active']));

        // Deactivate rules marked for deactivation
        $version->rules()
            ->wherePivot('deactivate_on_publish', true)
            ->where('rules.status', 'active')
            ->each(fn (Rule $rule) => $rule->update(['status' => 'inactive']));

        // Notify all active users that new rules require agreement
        $notificationService = app(TicketNotificationService::class);
        User::where('membership_level', '>=', MembershipLevel::Stowaway->value)
            ->each(function (User $user) use ($version, $notificationService) {
                $notificationService->send($user, new RulesVersionPublishedNotification($user, $version));
            });
    }
}
