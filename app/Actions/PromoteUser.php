<?php

namespace App\Actions;

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Models\User;
use App\Notifications\UserPromotedToResidentNotification;
use App\Notifications\UserPromotedToStowawayNotification;
use App\Notifications\UserPromotedToTravelerNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class PromoteUser
{
    use AsAction;

    /**
     * Promotes a user one membership level higher, stopping at the provided maximum level.
     *
     * Updates the user's membership_level and promoted_at, persists the change, records an activity,
     * synchronizes Minecraft ranks, and dispatches level-specific notifications.
     *
     * @param  MembershipLevel  $maxLevel  The highest membership level the user may be promoted to.
     */
    public function handle(User $user, MembershipLevel $maxLevel = MembershipLevel::Citizen)
    {
        $current = $user->membership_level;

        if ($current->value >= $maxLevel->value) {
            return;
        }

        $levels = MembershipLevel::cases();
        $currentIndex = array_search($current, $levels, strict: true);
        $nextLevel = $levels[$currentIndex + 1] ?? null;

        $user->membership_level = $nextLevel;
        $user->promoted_at = now();
        $user->save();

        \App\Actions\RecordActivity::handle($user, 'user_promoted', "Promoted from {$current->label()} to {$nextLevel->label()}.");

        SyncMinecraftRanks::run($user);

        $notificationService = app(TicketNotificationService::class);

        if ($nextLevel === MembershipLevel::Stowaway) {
            $notification = new UserPromotedToStowawayNotification($user);
            $staff = User::where(function ($q) {
                $q->where('staff_department', StaffDepartment::Quartermaster)
                    ->orWhere('staff_department', StaffDepartment::Command);
            })->whereNotNull('staff_rank')->get();

            foreach ($staff as $member) {
                $notificationService->send($member, clone $notification);
            }
        } elseif ($nextLevel === MembershipLevel::Traveler) {
            $notificationService->send($user, new UserPromotedToTravelerNotification($user));
        } elseif ($nextLevel === MembershipLevel::Resident) {
            $notificationService->send($user, new UserPromotedToResidentNotification($user));
        }
    }
}
