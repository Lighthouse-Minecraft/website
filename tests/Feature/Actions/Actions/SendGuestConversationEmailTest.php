<?php

declare(strict_types=1);

use App\Actions\SendGuestConversationEmail;
use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Notifications\ContactGuestReplyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses()->group('actions');

it('sends a notification to the guest email', function () {
    Notification::fake();

    $thread = Thread::create([
        'type' => ThreadType::ContactInquiry,
        'subject' => '[General Inquiry] Test subject',
        'status' => ThreadStatus::Open,
        'guest_email' => 'guest@example.com',
        'conversation_token' => Str::uuid(),
        'last_message_at' => now(),
    ]);

    $message = Message::create([
        'thread_id' => $thread->id,
        'body' => 'This is a staff reply.',
        'kind' => MessageKind::Message,
        'guest_email_sent' => false,
    ]);

    SendGuestConversationEmail::run($thread, $message);

    Notification::assertSentOnDemand(
        ContactGuestReplyNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'guest@example.com'
    );
});

it('sets guest_email_sent to true on the message', function () {
    Notification::fake();

    $thread = Thread::create([
        'type' => ThreadType::ContactInquiry,
        'subject' => '[General Inquiry] Test subject',
        'status' => ThreadStatus::Open,
        'guest_email' => 'guest@example.com',
        'conversation_token' => Str::uuid(),
        'last_message_at' => now(),
    ]);

    $message = Message::create([
        'thread_id' => $thread->id,
        'body' => 'Staff reply content.',
        'kind' => MessageKind::Message,
        'guest_email_sent' => false,
    ]);

    SendGuestConversationEmail::run($thread, $message);

    expect($message->fresh()->guest_email_sent)->toBeTrue();
});
