<?php

namespace App\Actions;

use App\Models\RuleVersion;
use App\Models\User;
use App\Notifications\RulesAgreementReminderNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class SendRulesAgreementReminder
{
    use AsAction;

    /**
     * Send a rules agreement reminder email to $user and record the timestamp.
     */
    public function handle(User $user, RuleVersion $version): void
    {
        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($user, new RulesAgreementReminderNotification($user, $version));

        $user->rules_reminder_sent_at = now();
        $user->save();
    }
}
