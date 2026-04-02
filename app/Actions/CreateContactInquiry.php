<?php

namespace App\Actions;

use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\ContactSubmissionConfirmationNotification;
use App\Notifications\NewContactInquiryNotification;
use App\Services\TicketNotificationService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateContactInquiry
{
    use AsAction;

    public function handle(
        string $name,
        string $email,
        string $category,
        string $subject,
        string $body
    ): Thread {
        $token = (string) Str::uuid();

        // Resolve staff recipients before the transaction (read-only query)
        $staffRecipients = User::query()
            ->where(fn ($q) => $q
                ->whereNotNull('admin_granted_at')
                ->orWhereHas('staffPosition', fn ($q) => $q->whereNotNull('has_all_roles_at'))
                ->orWhereHas('staffPosition.roles', fn ($q) => $q->where('name', 'Contact - Receive Submissions'))
            )
            ->get()
            ->unique('id');

        // Persist everything atomically
        $thread = DB::transaction(function () use ($name, $email, $category, $subject, $body, $token, $staffRecipients) {
            $thread = Thread::create([
                'type' => ThreadType::ContactInquiry,
                'subject' => "[{$category}] {$subject}",
                'status' => ThreadStatus::Open,
                'guest_name' => $name ?: null,
                'guest_email' => $email,
                'conversation_token' => $token,
                'last_message_at' => now(),
            ]);

            Message::create([
                'thread_id' => $thread->id,
                'body' => $body,
                'kind' => MessageKind::Message,
                'guest_email_sent' => false,
            ]);

            foreach ($staffRecipients as $staffUser) {
                $thread->addParticipant($staffUser);
            }

            RecordActivity::run($thread, 'contact_inquiry_received', 'Contact inquiry received.');

            return $thread;
        });

        // Send notifications after the transaction commits
        (new AnonymousNotifiable)
            ->route('mail', $email)
            ->notify(new ContactSubmissionConfirmationNotification(
                guestName: $name ?: 'Guest',
                subject: $subject,
                conversationToken: $token
            ));

        $notificationService = app(TicketNotificationService::class);
        $notification = new NewContactInquiryNotification($thread);

        foreach ($staffRecipients as $staffUser) {
            $notificationService->send($staffUser, clone $notification, 'staff_alerts');
        }

        return $thread;
    }
}
