<?php

namespace App\Actions;

use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitVersionForApproval
{
    use AsAction;

    /**
     * Move a draft version to submitted status for approval.
     */
    public function handle(RuleVersion $version, User $submittedBy): void
    {
        if ($version->status !== 'draft') {
            throw new AuthorizationException('Only draft versions can be submitted for approval.');
        }

        $version->status = 'submitted';
        $version->save();
    }
}
