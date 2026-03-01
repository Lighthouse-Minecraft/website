<?php

namespace App\Actions;

use App\Enums\BrigType;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class LockAccountForAgeVerification
{
    use AsAction;

    public function handle(User $target, User $admin): void
    {
        $target->date_of_birth = null;

        if (! $target->isInBrig()) {
            $target->save();
            PutUserInBrig::run(
                target: $target,
                admin: $admin,
                reason: 'Account locked: age verification required by staff.',
                brigType: BrigType::AgeLock,
                notify: false,
            );
        } else {
            $target->brig_type = BrigType::AgeLock;
            $target->brig_reason = 'Account locked: age verification required by staff.';
            $target->save();
        }

        RecordActivity::run($target, 'account_age_locked', "Account locked for age verification by {$admin->name}.");
    }
}
