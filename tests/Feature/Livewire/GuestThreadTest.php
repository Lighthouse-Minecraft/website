<?php

use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewContactInquiryNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

function makeGuestThread(array $attrs = []): Thread
{
    return Thread::create(array_merge([
        'type' => ThreadType::ContactInquiry,
        'subject' => '[General Inquiry] Guest thread test',
        'status' => ThreadStatus::Open,
        'guest_name' => 'Alice Guest',
        'guest_email' => 'alice@example.com',
        'conversation_token' => (string) Str::uuid(),
        'last_message_at' => now(),
    ], $attrs));
}

describe('Guest Thread Page', function () {
    it('is accessible without authentication', function () {
        $thread = makeGuestThread();
        get('/contact/thread/'.$thread->conversation_token)->assertOk();
    });

    it('returns 404 for an invalid token', function () {
        get('/contact/thread/invalid-token-that-does-not-exist')->assertNotFound();
    });

    it('returns 404 for a missing token', function () {
        get('/contact/thread/'.Str::uuid())->assertNotFound();
    });

    it('displays thread subject and category', function () {
        $thread = makeGuestThread([
            'subject' => '[Membership / Joining] How do I join?',
        ]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('How do I join?')
            ->assertSee('Membership / Joining');
    });

    it('displays messages in chronological order', function () {
        $staff = User::factory()->create(['name' => 'Staff Member']);
        $thread = makeGuestThread();

        Message::create([
            'thread_id' => $thread->id,
            'body' => 'Original inquiry body',
            'kind' => MessageKind::Message,
            'guest_email_sent' => false,
            'created_at' => now()->subMinutes(10),
        ]);

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $staff->id,
            'body' => 'Staff response here',
            'kind' => MessageKind::Message,
            'guest_email_sent' => true,
            'created_at' => now(),
        ]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $html = $component->html();

        expect(strpos($html, 'Original inquiry body'))->toBeLessThan(strpos($html, 'Staff response here'));
    });

    it('does not show internal notes in the DOM', function () {
        $staff = User::factory()->create();
        $thread = makeGuestThread();

        Message::create([
            'thread_id' => $thread->id,
            'body' => 'Visible reply to guest',
            'kind' => MessageKind::Message,
            'guest_email_sent' => false,
        ]);

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $staff->id,
            'body' => 'Secret internal staff note',
            'kind' => MessageKind::InternalNote,
            'guest_email_sent' => false,
        ]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('Visible reply to guest')
            ->assertDontSee('Secret internal staff note');
    });

    it('shows reply form for open threads', function () {
        $thread = makeGuestThread(['status' => ThreadStatus::Open]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('Send Reply');
    });

    it('shows reply form for pending threads', function () {
        $thread = makeGuestThread(['status' => ThreadStatus::Pending]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('Send Reply');
    });

    it('shows closed banner and hides reply form for closed threads', function () {
        $thread = makeGuestThread(['status' => ThreadStatus::Closed]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('conversation has been closed')
            ->assertSee('submit a new inquiry')
            ->assertDontSee('Send Reply');
    });

    it('shows closed banner and hides reply form for resolved threads', function () {
        $thread = makeGuestThread(['status' => ThreadStatus::Resolved]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('conversation has been closed')
            ->assertDontSee('Send Reply');
    });

    it('guest reply creates a message with guest_email_sent=false', function () {
        Notification::fake();

        $thread = makeGuestThread();

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->set('replyBody', 'This is a follow-up from the guest.')
            ->call('submitReply');

        $component->assertHasNoErrors();

        $message = $thread->messages()->latest()->first();
        expect($message)->not->toBeNull()
            ->and($message->body)->toBe('This is a follow-up from the guest.')
            ->and($message->kind)->toBe(MessageKind::Message)
            ->and($message->guest_email_sent)->toBeFalse()
            ->and($message->user_id)->toBeNull();
    });

    it('guest reply notifies staff participants', function () {
        Notification::fake();

        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $thread = makeGuestThread();
        $thread->addParticipant($staff);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->set('replyBody', 'A follow-up question from the guest.')
            ->call('submitReply');

        Notification::assertSentTo($staff, NewContactInquiryNotification::class);
    });

    it('guest messages are attributed to the guest name', function () {
        $thread = makeGuestThread(['guest_name' => 'Bob Visitor']);

        Message::create([
            'thread_id' => $thread->id,
            'body' => 'Message from Bob',
            'kind' => MessageKind::Message,
            'guest_email_sent' => false,
        ]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('Bob Visitor');
    });

    it('staff messages are attributed to the staff display name', function () {
        $staff = User::factory()->create(['name' => 'Staff Person Name']);
        $thread = makeGuestThread();

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $staff->id,
            'body' => 'Reply from staff',
            'kind' => MessageKind::Message,
            'guest_email_sent' => true,
        ]);

        $component = Volt::test('contact.guest-thread', ['token' => $thread->conversation_token]);
        $component->assertSee('Staff Person Name');
    });
});
