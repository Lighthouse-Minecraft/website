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

        // Send confirmation to guest
        (new AnonymousNotifiable)
            ->route('mail', $email)
            ->notify(new ContactSubmissionConfirmationNotification(
                guestName: $name ?: 'Guest',
                subject: $subject,
                conversationToken: $token
            ));

        // Notify staff with the "Contact - Receive Submissions" role
        $staffRecipients = User::query()
            ->where(fn ($q) => $q
                ->whereNotNull('admin_granted_at')
                ->orWhereHas('staffPosition', fn ($q) => $q->whereNotNull('has_all_roles_at'))
                ->orWhereHas('staffPosition.roles', fn ($q) => $q->where('name', 'Contact - Receive Submissions'))
            )
            ->get()
            ->unique('id');

        $notificationService = app(TicketNotificationService::class);
        $notification = new NewContactInquiryNotification($thread);

        foreach ($staffRecipients as $staffUser) {
            $thread->addParticipant($staffUser);
            $notificationService->send($staffUser, clone $notification, 'staff_alerts');
        }

        RecordActivity::run($thread, 'contact_inquiry_received', "Contact inquiry received from {$email}.");

        return $thread;
    }
}
