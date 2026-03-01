<?php

namespace App\Actions;

use App\Models\ParentChildLink;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateChildAccount
{
    use AsAction;

    public function handle(User $parent, string $name, string $email, string $dateOfBirth): User
    {
        $isUnder13 = \Carbon\Carbon::parse($dateOfBirth)->age < 13;

        $child = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
            'date_of_birth' => $dateOfBirth,
            'parent_email' => $parent->email,
            'parent_allows_site' => true,
            'parent_allows_minecraft' => ! $isUnder13,
            'parent_allows_discord' => ! $isUnder13,
        ]);

        ParentChildLink::create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);

        $resetStatus = Password::sendResetLink(['email' => $email]);

        if ($resetStatus !== Password::RESET_LINK_SENT) {
            Log::error('Failed to send password reset link for child account', [
                'child_id' => $child->id,
                'email' => $email,
                'status' => $resetStatus,
            ]);

            $child->delete();

            throw new \RuntimeException('Failed to send password reset email. Child account was not created.');
        }

        RecordActivity::run($child, 'child_account_created', "Account created by parent {$parent->name}.");

        return $child;
    }
}
