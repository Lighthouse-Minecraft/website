<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AssignCredentialPositions
{
    use AsAction;

    /**
     * @param  array<int>  $positionIds
     */
    public function handle(Credential $credential, User $assignedBy, array $positionIds): void
    {
        $credential->staffPositions()->sync($positionIds);

        RecordActivity::run(
            $credential,
            'credential_positions_assigned',
            "Credential \"{$credential->name}\" position access updated by {$assignedBy->name}."
        );
    }
}
