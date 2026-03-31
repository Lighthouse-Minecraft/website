<?php

namespace App\Jobs;

use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\SiteConfig;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\TicketEscalationNotification;
use App\Services\TicketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EscalateUnassignedTickets implements ShouldQueue
{
    use Queueable;

    public function handle(TicketNotificationService $service): void
    {
        $thresholdMinutes = (int) SiteConfig::getValue('ticket_escalation_threshold_minutes', '30');

        if ($thresholdMinutes <= 0) {
            return;
        }

        $tickets = Thread::where('type', ThreadType::Ticket)
            ->where('status', ThreadStatus::Open)
            ->whereNull('assigned_to_user_id')
            ->whereNull('escalated_at')
            ->where('created_at', '<=', now()->subMinutes($thresholdMinutes))
            ->with(['createdBy'])
            ->get();

        if ($tickets->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereNotNull('admin_granted_at')
            ->orWhereHas('staffPosition', fn ($q) => $q->whereNotNull('has_all_roles_at'))
            ->orWhereHas('staffPosition.roles', fn ($q) => $q->where('name', 'Ticket Escalation - Receiver'))
            ->get()
            ->filter(fn ($user) => $user->can('receive-ticket-escalations'));

        if ($recipients->isEmpty()) {
            return;
        }

        $systemUser = User::where('email', 'system@lighthouse.local')->first();

        foreach ($tickets as $ticket) {
            foreach ($recipients as $recipient) {
                $service->send($recipient, new TicketEscalationNotification($ticket), 'staff_alerts');
            }

            if ($systemUser) {
                Message::create([
                    'thread_id' => $ticket->id,
                    'user_id' => $systemUser->id,
                    'body' => 'This ticket has been escalated — no staff response was received within the expected timeframe.',
                    'kind' => MessageKind::System,
                ]);
            }

            $ticket->update(['escalated_at' => now()]);
        }
    }
}
