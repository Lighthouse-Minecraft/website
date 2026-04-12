<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteCredential
{
    use AsAction;

    public function handle(Credential $credential, User $deletedBy): void
    {
        $name = $credential->name;

        // All operations are atomic: if delete fails, no orphaned audit entry is left
        DB::transaction(function () use ($credential, $deletedBy, $name) {
            // Audit entry is preserved with credential_id = null (nullOnDelete FK)
            RecordCredentialAccess::run($credential, $deletedBy, 'deleted');
            $credential->staffPositions()->detach();
            $credential->delete();
            RecordActivity::run($deletedBy, 'credential_deleted', "Credential \"{$name}\" deleted by {$deletedBy->name}.", $deletedBy);
        });
    }
}
