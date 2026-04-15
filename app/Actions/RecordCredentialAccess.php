<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\CredentialAccessLog;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordCredentialAccess
{
    use AsAction;

    public function handle(Credential $credential, User $user, string $action): void
    {
        CredentialAccessLog::create([
            'credential_id' => $credential->id,
            'user_id' => $user->id,
            'action' => $action,
            'created_at' => now(),
        ]);
    }
}
