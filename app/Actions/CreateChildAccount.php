<?php

namespace App\Actions;

use App\Models\ParentChildLink;
use App\Models\User;
use App\Notifications\ChildWelcomeNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateChildAccount
{
    use AsAction;

    public function handle(User $parent, string $name, string $email, string $dateOfBirth): User
    {
        $isUnder13 = \Carbon\Carbon::parse($dateOfBirth)->age < 13;

        $child = DB::transaction(function () use ($parent, $name, $email, $dateOfBirth, $isUnder13) {
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

            return $child;
        });

        RecordActivity::run($child, 'child_account_created', "Account created by parent {$parent->name}.");

        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($child, new ChildWelcomeNotification($child, $parent), 'account');

        return $child;
    }
}
