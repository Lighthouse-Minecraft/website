<?php

declare(strict_types=1);

use App\Actions\CreateContactInquiry;
use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\ContactSubmissionConfirmationNotification;
use App\Notifications\NewContactInquiryNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('actions');

it('creates a contact inquiry thread with correct fields', function () {
    Notification::fake();

    $thread = CreateContactInquiry::run(
        name: 'Alice',
        email: 'alice@example.com',
        category: 'General Inquiry',
        subject: 'Hello there',
        body: 'This is my message to the team.',
    );

    expect($thread)->toBeInstanceOf(Thread::class)
        ->and($thread->type)->toBe(ThreadType::ContactInquiry)
        ->and($thread->status)->toBe(ThreadStatus::Open)
        ->and($thread->guest_name)->toBe('Alice')
        ->and($thread->guest_email)->toBe('alice@example.com')
        ->and($thread->conversation_token)->not->toBeNull()
        ->and($thread->subject)->toBe('[General Inquiry] Hello there');
});

it('creates a UUID conversation token', function () {
    Notification::fake();

    $thread = CreateContactInquiry::run(
        name: '',
        email: 'test@example.com',
        category: 'Technical Issue',
        subject: 'Test subject',
        body: 'Test message body here.',
    );

    expect($thread->conversation_token)->toMatch('/^[0-9a-f-]{36}$/i');
});

it('stores null guest_name when name is empty', function () {
    Notification::fake();

    $thread = CreateContactInquiry::run(
        name: '',
        email: 'anon@example.com',
        category: 'General Inquiry',
        subject: 'Anonymous inquiry',
        body: 'Message from anonymous user.',
    );

    expect($thread->guest_name)->toBeNull();
});

it('creates a message with the inquiry body', function () {
    Notification::fake();

    $thread = CreateContactInquiry::run(
        name: 'Bob',
        email: 'bob@example.com',
        category: 'Membership / Joining',
        subject: 'How do I join?',
        body: 'I want to join the server please.',
    );

    $message = $thread->messages()->first();
    expect($message)->not->toBeNull()
        ->and($message->body)->toBe('I want to join the server please.')
        ->and($message->kind)->toBe(MessageKind::Message)
        ->and($message->guest_email_sent)->toBeFalse();
});

it('sends a confirmation notification to the guest email', function () {
    Notification::fake();

    CreateContactInquiry::run(
        name: 'Carol',
        email: 'carol@example.com',
        category: 'General Inquiry',
        subject: 'My question',
        body: 'Here is my detailed question.',
    );

    Notification::assertSentOnDemand(
        ContactSubmissionConfirmationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'carol@example.com'
            || (is_array($notifiable->routes['mail']) && in_array('carol@example.com', $notifiable->routes['mail']))
    );
});

it('notifies staff with the Contact - Receive Submissions role', function () {
    Notification::fake();

    $staffUser = User::factory()->withRole('Contact - Receive Submissions')->create();

    CreateContactInquiry::run(
        name: 'Dave',
        email: 'dave@example.com',
        category: 'Report a Concern',
        subject: 'Problem report',
        body: 'I have a concern to report.',
    );

    Notification::assertSentTo($staffUser, NewContactInquiryNotification::class);
});

it('adds staff as thread participants', function () {
    Notification::fake();

    $staffUser = User::factory()->withRole('Contact - Receive Submissions')->create();

    $thread = CreateContactInquiry::run(
        name: 'Eve',
        email: 'eve@example.com',
        category: 'General Inquiry',
        subject: 'Participant test',
        body: 'Testing that staff are added as participants.',
    );

    expect($thread->participants()->where('user_id', $staffUser->id)->exists())->toBeTrue();
});

it('logs contact_inquiry_received activity', function () {
    Notification::fake();

    $thread = CreateContactInquiry::run(
        name: 'Frank',
        email: 'frank@example.com',
        category: 'General Inquiry',
        subject: 'Activity log test',
        body: 'Testing that activity is logged correctly.',
    );

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => Thread::class,
        'subject_id' => $thread->id,
        'action' => 'contact_inquiry_received',
    ]);
});

it('notifies admin users even without the role', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    CreateContactInquiry::run(
        name: 'Grace',
        email: 'grace@example.com',
        category: 'General Inquiry',
        subject: 'Admin notification test',
        body: 'Admins should always be notified.',
    );

    Notification::assertSentTo($admin, NewContactInquiryNotification::class);
});
