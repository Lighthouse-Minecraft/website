<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Models\ActivityLog;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

describe('Ticket Activity Logging', function () {
    beforeEach(function () {
        // Ensure system user exists
        User::factory()->create([
            'email' => 'system@lighthouse.local',
            'name' => 'System',
        ]);
    });

    it('logs activity when a ticket is opened', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.create-ticket')
            ->set('department', StaffDepartment::Chaplain->value)
            ->set('subject', 'Test Ticket')
            ->set('message', 'Test message body')
            ->call('createTicket');

        $thread = Thread::where('subject', 'Test Ticket')->first();

        $activity = ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->where('action', 'ticket_opened')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toBe('Opened ticket: Test Ticket')
            ->and($activity->causer_id)->toBe($user->id);
    })->done();

    it('logs activity when staff joins a ticket by replying', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, \App\Enums\StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Help Needed']);

        actingAs($staff);

        // Staff can view department tickets, so they can reply without being a participant
        // This will make them join as a participant
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Staff helping out')
            ->call('sendReply');

        $activity = ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->where('action', 'ticket_joined')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toBe('Joined ticket: Help Needed')
            ->and($activity->causer_id)->toBe($staff->id);
    })->done();

    it('logs activity when viewer becomes participant', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, \App\Enums\StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();

        actingAs($staff);

        // Staff views ticket (becomes viewer)
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread]);

        $participant = $thread->participants()->where('user_id', $staff->id)->first();
        expect($participant->is_viewer)->toBeTrue();

        // Clear any existing activity logs from viewing
        ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->delete();

        // Staff replies (becomes full participant)
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'Staff reply')
            ->call('sendReply');

        $activity = ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->where('action', 'ticket_joined')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toBe('Joined ticket: '.$thread->subject)
            ->and($activity->causer_id)->toBe($staff->id);
    })->done();

    it('does not log activity for regular replies', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->create();
        $thread->addParticipant($user);

        actingAs($user);

        // Clear any existing activity
        ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->delete();

        // Send a reply
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'This is a regular reply')
            ->call('sendReply');

        // Should not log message_sent or internal_note_added
        $activity = ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->whereIn('action', ['message_sent', 'internal_note_added'])
            ->first();

        expect($activity)->toBeNull();
    })->done();

    it('does not log activity for internal notes', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, \App\Enums\StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $thread->addParticipant($staff);

        actingAs($staff);

        // Clear any existing activity
        ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->delete();

        // Send an internal note
        Volt::test('ready-room.tickets.view-ticket', ['thread' => $thread])
            ->set('replyMessage', 'This is an internal note')
            ->set('isInternalNote', true)
            ->call('sendReply');

        // Should not log internal_note_added
        $activity = ActivityLog::where('subject_type', Thread::class)
            ->where('subject_id', $thread->id)
            ->where('action', 'internal_note_added')
            ->first();

        expect($activity)->toBeNull();
    })->done();
});
