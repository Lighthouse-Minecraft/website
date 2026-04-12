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

        // Record access audit before deleting — access log rows are preserved with
        // credential_id set to null (nullOnDelete FK) so the history is not lost
        RecordCredentialAccess::run($credential, $deletedBy, 'deleted');

        $credential->staffPositions()->detach();
        $credential->delete();

        RecordActivity::run($deletedBy, 'credential_deleted', "Credential \"{$name}\" deleted by {$deletedBy->name}.", $deletedBy);
    }
}
