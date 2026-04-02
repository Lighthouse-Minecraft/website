<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\Thread;
use App\Notifications\ContactGuestReplyNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Lorisleiva\Actions\Concerns\AsAction;

class SendGuestConversationEmail
{
    use AsAction;

    public function handle(Thread $thread, Message $message): void
    {
        (new AnonymousNotifiable)
            ->route('mail', $thread->guest_email)
            ->notify(new ContactGuestReplyNotification($thread, $message));

        $message->update(['guest_email_sent' => true]);
    }
}
