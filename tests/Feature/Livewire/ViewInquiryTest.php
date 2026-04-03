<?php

use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\ContactGuestReplyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

function makeContactThread(array $attrs = []): Thread
{
    return Thread::create(array_merge([
        'type' => ThreadType::ContactInquiry,
        'subject' => '[General Inquiry] Test inquiry',
        'status' => ThreadStatus::Open,
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'conversation_token' => Str::uuid(),
        'last_message_at' => now(),
    ], $attrs));
}

describe('View Inquiry Component', function () {
    it('requires authentication', function () {
        $thread = makeContactThread();
        get('/contact-inquiries/'.$thread->id)->assertRedirect('/login');
    });

    it('denies access to users without the role', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $thread = makeContactThread();
        get('/contact-inquiries/'.$thread->id)->assertForbidden();
    });

    it('allows access to users with the Contact - Receive Submissions role', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();
        get('/contact-inquiries/'.$thread->id)->assertOk();
    });

    it('returns 404 for non-contact-inquiry threads', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = Thread::create([
            'type' => \App\Enums\ThreadType::Ticket,
            'subject' => 'Regular ticket',
            'status' => ThreadStatus::Open,
            'created_by_user_id' => $staff->id,
            'department' => \App\Enums\StaffDepartment::Command,
            'last_message_at' => now(),
        ]);

        get('/contact-inquiries/'.$thread->id)->assertNotFound();
    });

    it('displays thread header with guest info and category', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread([
            'subject' => '[Parent / Guardian Question] School question',
            'guest_name' => 'Jane Parent',
            'guest_email' => 'jane@example.com',
        ]);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->assertSee('School question')
            ->assertSee('Parent / Guardian Question')
            ->assertSee('Jane Parent')
            ->assertSee('jane@example.com');
    });

    it('displays messages in chronological order', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();

        Message::create([
            'thread_id' => $thread->id,
            'body' => 'First guest message',
            'kind' => MessageKind::Message,
            'guest_email_sent' => false,
            'created_at' => now()->subMinutes(5),
        ]);

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $staff->id,
            'body' => 'Staff reply message',
            'kind' => MessageKind::Message,
            'guest_email_sent' => true,
            'created_at' => now(),
        ]);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $html = $component->html();

        expect(strpos($html, 'First guest message'))->toBeLessThan(strpos($html, 'Staff reply message'));
    });

    it('shows emailed indicator for messages where guest_email_sent is true', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $staff->id,
            'body' => 'Emailed reply',
            'kind' => MessageKind::Message,
            'guest_email_sent' => true,
        ]);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->assertSee('Emailed to guest');
    });

    it('shows internal note with amber styling indicator', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $staff->id,
            'body' => 'Private staff note',
            'kind' => MessageKind::InternalNote,
            'guest_email_sent' => false,
        ]);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->assertSee('Internal Note')
            ->assertSee('Private staff note');
    });

    it('submitting a reply with email ON sends guest notification and sets guest_email_sent', function () {
        Notification::fake();

        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();
        $thread->addParticipant($staff);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('replyBody', 'This is a staff reply to the guest.')
            ->set('isInternalNote', false)
            ->set('emailGuest', true)
            ->call('sendReply');

        $component->assertHasNoErrors();

        $message = $thread->messages()->latest()->first();
        expect($message)->not->toBeNull()
            ->and($message->kind)->toBe(MessageKind::Message)
            ->and($message->guest_email_sent)->toBeTrue();

        Notification::assertSentOnDemand(ContactGuestReplyNotification::class);
    });

    it('submitting a reply with email OFF saves message without emailing guest', function () {
        Notification::fake();

        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();
        $thread->addParticipant($staff);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('replyBody', 'Staff message, no email.')
            ->set('isInternalNote', false)
            ->set('emailGuest', false)
            ->call('sendReply');

        $component->assertHasNoErrors();

        $message = $thread->messages()->latest()->first();
        expect($message->guest_email_sent)->toBeFalse();

        Notification::assertNothingSent();
    });

    it('submitting an internal note does not email guest and sets InternalNote kind', function () {
        Notification::fake();

        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();
        $thread->addParticipant($staff);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('replyBody', 'Internal note content.')
            ->set('isInternalNote', true)
            ->call('sendReply');

        $component->assertHasNoErrors();

        $message = $thread->messages()->latest()->first();
        expect($message->kind)->toBe(MessageKind::InternalNote)
            ->and($message->guest_email_sent)->toBeFalse();

        Notification::assertNothingSent();
    });

    it('email toggle is hidden when internal note is selected', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('isInternalNote', true);

        $component->assertDontSee('Email guest');
    });

    it('email toggle is visible when reply is selected', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('isInternalNote', false);

        $component->assertSee('Email guest');
    });

    it('hides reply form when thread is closed', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread(['status' => ThreadStatus::Closed]);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->assertDontSee('Send Reply')
            ->assertDontSee('Add Note')
            ->assertSee('closed');
    });

    it('can change thread status to Resolved', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('newStatus', 'resolved')
            ->call('changeStatus');

        expect($thread->fresh()->status)->toBe(ThreadStatus::Resolved);
    });

    it('can change thread status to Closed', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread();

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('newStatus', 'closed')
            ->call('changeStatus');

        expect($thread->fresh()->status)->toBe(ThreadStatus::Closed);
    });

    it('rejects sendReply server-side when thread is closed', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = makeContactThread(['status' => ThreadStatus::Closed]);

        $component = Volt::test('contact.view-inquiry', ['thread' => $thread]);
        $component->set('replyBody', 'Sneaky reply attempt')
            ->call('sendReply');

        expect(Message::where('thread_id', $thread->id)->where('body', 'Sneaky reply attempt')->exists())->toBeFalse();
    });
});
