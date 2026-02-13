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
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

describe('View Ticket Component', function () {
    beforeEach(function () {
        // Ensure system user exists
        User::factory()->create([
            'email' => 'system@lighthouse.local',
            'name' => 'System',
        ]);
    });

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
});
