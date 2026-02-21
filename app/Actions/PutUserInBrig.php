<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\User;
use App\Notifications\UserPutInBrigNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class PutUserInBrig
{
    use AsAction;

    public function handle(User $target, User $admin, string $reason, ?Carbon $expiresAt = null): void
    {
        $target->in_brig = true;
        $target->brig_reason = $reason;
        $target->brig_expires_at = $expiresAt;
        $target->brig_timer_notified = false;
        $target->save();

        // Ban all active/verifying Minecraft accounts
        foreach ($target->minecraftAccounts()->whereIn('status', [MinecraftAccountStatus::Active->value, MinecraftAccountStatus::Verifying->value])->get() as $account) {
            SendMinecraftCommand::run(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $account->command_id,
                $target
            );
            $account->status = MinecraftAccountStatus::Banned;
            $account->save();
        }

        $description = "Put in the brig by {$admin->name}. Reason: {$reason}.";
        if ($expiresAt) {
            $description .= " Timer set until {$expiresAt->toDateTimeString()}.";
        } else {
            $description .= ' No timer set.';
        }

        RecordActivity::handle($target, 'user_put_in_brig', $description);

        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($target, new UserPutInBrigNotification($target, $reason, $expiresAt));
    }
}
