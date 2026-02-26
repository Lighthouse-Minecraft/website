<?php

namespace App\Policies;

use App\Models\DiscordAccount;
use App\Models\User;

class DiscordAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, DiscordAccount $discordAccount): bool
    {
        return $user->id === $discordAccount->user_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DiscordAccount $discordAccount): bool
    {
        return false;
    }

    public function delete(User $user, DiscordAccount $discordAccount): bool
    {
        return $user->id === $discordAccount->user_id || $user->isAdmin();
    }
}
