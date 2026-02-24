<?php

namespace App\Actions;

use App\Enums\MembershipLevel;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DemoteUser
{
    use AsAction;

    public function handle(User $user, MembershipLevel $minLevel = MembershipLevel::Drifter)
    {
        $current = $user->membership_level;

        if ($current->value <= $minLevel->value) {
            return;
        }

        $levels = MembershipLevel::cases();
        $currentIndex = array_search($current, $levels, strict: true);

        if ($currentIndex === false || $currentIndex === 0) {
            return;
        }

        $previousLevel = $levels[$currentIndex - 1];

        $user->membership_level = $previousLevel;
        $user->save();

        RecordActivity::handle($user, 'user_demoted', "Demoted from {$current->label()} to {$previousLevel->label()}.");

        SyncMinecraftPermissions::run($user);
    }
}
