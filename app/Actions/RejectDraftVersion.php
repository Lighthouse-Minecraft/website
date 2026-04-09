<?php

namespace App\Actions;

use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

class RejectDraftVersion
{
    use AsAction;

    /**
     * Reject a submitted version and move it back to draft with a rejection note.
     */
    public function handle(RuleVersion $version, User $rejectedBy, string $rejectionNote): void
    {
        if ($version->status !== 'submitted') {
            throw new AuthorizationException('Only submitted versions can be rejected.');
        }

        $version->status = 'draft';
        $version->rejection_note = $rejectionNote;
        $version->save();
    }
}
