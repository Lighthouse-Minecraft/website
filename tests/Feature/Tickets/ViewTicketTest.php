<?php

declare(strict_types=1);

use App\Actions\FlagMessage;
use App\Enums\MessageFlagStatus;
use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadStatus;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

describe('View Ticket Component', function () {
    it('can render for participants', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $thread->addParticipant($user);

        Message::factory()->forThread($thread)->byUser($user)->create(['body' => 'Test message']);

        actingAs($user);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->assertSee($thread->subject)
            ->assertSee('Test message');
    })->done();

    it('can render for staff in department', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        Message::factory()->forThread($thread)->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->assertSee($thread->subject);
    })->done();

    it('hides internal notes from non-staff users', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $thread->addParticipant($user);

        Message::factory()->forThread($thread)->create(['body' => 'Public message', 'kind' => MessageKind::Message]);
        Message::factory()->forThread($thread)->create(['body' => 'Internal staff note', 'kind' => MessageKind::InternalNote]);

        actingAs($user);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->assertSee('Public message')
            ->assertDontSee('Internal staff note');
    })->done();

    it('shows internal notes to staff', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        Message::factory()->forThread($thread)->create(['body' => 'Public message', 'kind' => MessageKind::Message]);
        Message::factory()->forThread($thread)->create(['body' => 'Internal staff note', 'kind' => MessageKind::InternalNote]);

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->assertSee('Public message')
            ->assertSee('Internal staff note');
    })->done();

    it('allows staff to change ticket status', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->call('changeStatus', ThreadStatus::Resolved->value)
            ->assertHasNoErrors();

        $thread->refresh();
        expect($thread->status)->toBe(ThreadStatus::Resolved);
    })->done();

    it('allows officers to assign tickets', function () {
        $officer = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        $assignee = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        actingAs($officer);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->call('assignTo', $assignee->id)
            ->assertHasNoErrors();

        $thread->refresh();
        expect($thread->assigned_to_user_id)->toBe($assignee->id);
    })->done();

    it('allows assigning tickets to staff from different departments', function () {
        $commandOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
            ->create();

        $engineerStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)
            ->create();

        // Chaplain ticket assigned to Engineer staff
        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        actingAs($commandOfficer);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->call('assignTo', $engineerStaff->id)
            ->assertHasNoErrors();

        $thread->refresh();
        expect($thread->assigned_to_user_id)->toBe($engineerStaff->id);
    })->done();

    it('allows participants to reply to tickets', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->create();
        $thread->addParticipant($user);

        actingAs($user);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'This is my reply')
            ->call('sendReply')
            ->assertHasNoErrors();

        $reply = Message::where('thread_id', $thread->id)
            ->where('body', 'This is my reply')
            ->first();

        expect($reply)->not->toBeNull()
            ->and($reply->user_id)->toBe($user->id)
            ->and($reply->kind)->toBe(MessageKind::Message);
    })->done();

    it('allows staff to create internal notes', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Internal staff note')
            ->set('isInternalNote', true)
            ->call('sendReply')
            ->assertHasNoErrors();

        $note = Message::where('thread_id', $thread->id)
            ->where('body', 'Internal staff note')
            ->first();

        expect($note)->not->toBeNull()
            ->and($note->kind)->toBe(MessageKind::InternalNote);
    })->done();

    it('adds replying staff as participant if not already', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        expect($thread->participants()->where('user_id', $staff->id)->exists())->toBeFalse();

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Staff reply')
            ->call('sendReply');

        expect($thread->participants()->where('user_id', $staff->id)->exists())->toBeTrue();
    })->done();

    it('allows users to flag messages', function () {
        $user = User::factory()->create();
        $author = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $thread->addParticipant($user);

        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($user);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('flaggingMessageId', $message->id)
            ->set('flagReason', 'This is inappropriate')
            ->call('submitFlag')
            ->assertHasNoErrors();

        $flag = MessageFlag::where('message_id', $message->id)->first();

        expect($flag)->not->toBeNull()
            ->and($flag->flagged_by_user_id)->toBe($user->id)
            ->and($flag->note)->toBe('This is inappropriate');
    })->done();

    it('Quartermaster has viewFlagged permission', function () {
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        // Quartermaster has viewFlagged permission which allows seeing flags
        expect($quartermaster->can('viewFlagged', Thread::class))->toBeTrue();
    })->done();

    it('allows Quartermaster to acknowledge flags', function () {
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        $author = User::factory()->create();
        $flagger = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);
        FlagMessage::run($message, $flagger, 'Inappropriate content');

        $flag = MessageFlag::where('message_id', $message->id)->first();
        $thread->refresh();

        actingAs($quartermaster);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('acknowledgingFlagId', $flag->id)
            ->set('staffNotes', 'Reviewed and handled appropriately')
            ->call('acknowledgeFlag')
            ->assertHasNoErrors();

        $flag->refresh();

        expect($flag->status)->toBe(MessageFlagStatus::Acknowledged)
            ->and($flag->reviewed_by_user_id)->toBe($quartermaster->id)
            ->and($flag->staff_notes)->toBe('Reviewed and handled appropriately');
    })->done();

    it('adds staff as participant when they view department ticket as observer', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        // Staff is not yet a participant
        expect($thread->participants()->where('user_id', $staff->id)->exists())->toBeFalse();

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->assertSee($thread->subject);

        // Staff should now be added as a viewer (not a full participant)
        $participant = $thread->participants()->where('user_id', $staff->id)->first();
        expect($participant)->not->toBeNull()
            ->and($participant->is_viewer)->toBeTrue();
    })->done();

    it('marks ticket as read for observer viewing it', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['last_message_at' => now()]);

        // Staff is not yet a participant, so ticket appears unread
        expect($thread->isUnreadFor($staff))->toBeTrue();

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread]);

        // After viewing, should be marked as read
        $thread->refresh();
        expect($thread->isUnreadFor($staff))->toBeFalse();
    })->done();

    it('converts viewer to participant when they reply', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        // Staff views ticket first (becomes a viewer)
        actingAs($staff);
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread]);

        $participant = $thread->participants()->where('user_id', $staff->id)->first();
        expect($participant->is_viewer)->toBeTrue();

        // Now staff replies
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Staff reply')
            ->call('sendReply');

        // Should now be a full participant, not a viewer
        $participant->refresh();
        expect($participant->is_viewer)->toBeFalse();
    })->done();

    it('does not notify viewers when new message is posted', function () {
        Notification::fake();

        $user = User::factory()->create();
        $viewer = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $thread->addParticipant($user, isViewer: false); // Real participant
        $thread->addViewer($viewer); // Just a viewer

        actingAs($user);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'New message')
            ->call('sendReply');

        // Viewer should not get notification
        Notification::assertNothingSentTo($viewer);
    })->done();

    it('posts message and closes ticket when close button clicked with text', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $user = User::factory()->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create();
        $thread->addParticipant($user);

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Final response before closing')
            ->call('closeTicket');

        // Verify the message was posted
        $messages = $thread->fresh()->messages;
        expect($messages)->toHaveCount(2); // User's message + system message

        $userMessage = $messages->where('kind', MessageKind::Message)->first();
        expect($userMessage->body)->toBe('Final response before closing');
        expect($userMessage->user_id)->toBe($staff->id);

        // Verify the ticket was closed
        expect($thread->fresh()->status)->toBe(ThreadStatus::Closed);

        // Verify system message was created
        $systemMessage = $messages->where('kind', MessageKind::System)->first();
        expect($systemMessage->body)->toContain('closed this ticket');
    })->done();

    it('closes ticket without posting when close button clicked with empty text', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', '')
            ->call('closeTicket');

        // Verify only system message exists (no user message)
        $messages = $thread->fresh()->messages;
        expect($messages)->toHaveCount(1);
        expect($messages->first()->kind)->toBe(MessageKind::System);

        // Verify the ticket was closed
        expect($thread->fresh()->status)->toBe(ThreadStatus::Closed);
    })->done();

    it('clears ticket caches when reply is sent', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $thread->addParticipant($user);

        actingAs($user);

        // Prime the cache
        $user->ticketCounts();
        expect(\Illuminate\Support\Facades\Cache::has("user.{$user->id}.ticket_counts"))->toBeTrue();

        // Send a reply
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Test reply')
            ->call('sendReply');

        // Verify cache was cleared
        expect(\Illuminate\Support\Facades\Cache::has("user.{$user->id}.ticket_counts"))->toBeFalse();
    })->done();

    it('counts participant tickets correctly - non-closed or unread closed', function () {
        $user = User::factory()->create();

        // Create non-closed tickets (should be counted)
        $openThread = Thread::factory()->withStatus(ThreadStatus::Open)->create();
        $openThread->addParticipant($user);
        $pendingThread = Thread::factory()->withStatus(ThreadStatus::Pending)->create();
        $pendingThread->addParticipant($user);
        $resolvedThread = Thread::factory()->withStatus(ThreadStatus::Resolved)->create();
        $resolvedThread->addParticipant($user);

        // Create closed ticket with unread message (should be counted)
        $closedUnreadThread = Thread::factory()->withStatus(ThreadStatus::Closed)->create();
        $closedUnreadThread->addParticipant($user);
        // Don't mark as read - should be counted

        // Create closed ticket that's been read (should NOT be counted)
        $closedReadThread = Thread::factory()->withStatus(ThreadStatus::Closed)->create();
        $closedReadThread->addParticipant($user);
        $participant = $closedReadThread->participants()->where('user_id', $user->id)->first();
        $participant->update(['last_read_at' => now()->addMinute()]); // Mark as read after last message

        // Should count: 3 non-closed + 1 unread closed = 4
        $counts = $user->ticketCounts();
        expect($counts['badge'])->toBe(4);
    })->done();

    it('preserves filter parameter in back button URL', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->create();
        $thread->addParticipant($user);

        actingAs($user);

        // Test with filter parameter
        $component = Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('filter', 'my-open');

        expect($component->get('backUrl'))->toBe('/tickets?filter=my-open');

        // Test without filter parameter
        $componentNoFilter = Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread]);
        expect($componentNoFilter->get('backUrl'))->toBe('/tickets');
    })->done();

    it('displays back button with correct filter URL', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->create();
        $thread->addParticipant($user);

        actingAs($user);

        $component = Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('filter', 'my-closed');

        $html = $component->call('$refresh')->html();
        expect($html)->toContain('href="/tickets?filter=my-closed"')
            ->toContain('← Back to Tickets');
    })->done();

    it('auto-assigns unassigned ticket when staff replies', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();
        $creator = User::factory()->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create(['created_by_user_id' => $creator->id, 'assigned_to_user_id' => null]);
        $thread->addParticipant($creator);

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Staff handling this ticket')
            ->call('sendReply');

        $thread->refresh();
        expect($thread->assigned_to_user_id)->toBe($staff->id);
    })->done();

    it('does not change assignment when ticket is already assigned', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();
        $otherStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();
        $creator = User::factory()->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create(['created_by_user_id' => $creator->id, 'assigned_to_user_id' => $otherStaff->id]);
        $thread->addParticipant($creator);

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Another staff replying')
            ->call('sendReply');

        $thread->refresh();
        expect($thread->assigned_to_user_id)->toBe($otherStaff->id);
    })->done();

    it('does not auto-assign when creator replies to own ticket', function () {
        $creator = User::factory()->create();

        $thread = Thread::factory()
            ->withStatus(ThreadStatus::Open)
            ->create(['created_by_user_id' => $creator->id, 'assigned_to_user_id' => null]);
        $thread->addParticipant($creator);

        actingAs($creator);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Creator following up')
            ->call('sendReply');

        $thread->refresh();
        expect($thread->assigned_to_user_id)->toBeNull();
    })->done();

    it('does not auto-assign on internal note', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();
        $creator = User::factory()->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create(['created_by_user_id' => $creator->id, 'assigned_to_user_id' => null]);
        $thread->addParticipant($creator);

        actingAs($staff);

        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Internal staff note')
            ->set('isInternalNote', true)
            ->call('sendReply');

        $thread->refresh();
        expect($thread->assigned_to_user_id)->toBeNull();
    })->done();
});
