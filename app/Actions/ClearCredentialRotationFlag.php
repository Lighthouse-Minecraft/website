<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearCredentialRotationFlag
{
    use AsAction;

    public function handle(Credential $credential, User $actor): Credential
    {
        $credential->update(['needs_password_change' => false]);

        RecordActivity::run($credential, 'credential_rotation_flag_cleared', "Rotation flag manually cleared on \"{$credential->name}\" by {$actor->name}.", $actor);
        RecordCredentialAccess::run($credential, $actor, 'cleared_rotation_flag');

        return $credential->fresh();
    }
}
