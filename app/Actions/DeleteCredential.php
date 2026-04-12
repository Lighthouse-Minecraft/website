<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteCredential
{
    use AsAction;

    public function handle(Credential $credential, User $deletedBy): void
    {
        $name = $credential->name;

        // Record access before logs are cleared
        RecordCredentialAccess::run($credential, $deletedBy, 'deleted');

        $credential->staffPositions()->detach();
        $credential->accessLogs()->delete();
        $credential->delete();

        RecordActivity::run($deletedBy, 'credential_deleted', "Credential \"{$name}\" deleted by {$deletedBy->name}.");
    }
}
