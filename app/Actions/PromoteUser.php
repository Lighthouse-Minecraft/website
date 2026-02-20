<?php

namespace App\Actions;

use App\Enums\MembershipLevel;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class PromoteUser
{
    use AsAction;

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
    }
}
