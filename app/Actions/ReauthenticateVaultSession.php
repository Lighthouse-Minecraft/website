<?php

namespace App\Actions;

use App\Models\User;
use App\Services\VaultSession;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;

class ReauthenticateVaultSession
{
    use AsAction;

    public function handle(User $user, string $password): bool
    {
        if (! Hash::check($password, $user->password)) {
            return false;
        }

        app(VaultSession::class)->unlock();

        return true;
    }
}
