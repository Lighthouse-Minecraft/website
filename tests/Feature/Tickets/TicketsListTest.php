<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadStatus;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

describe('Tickets List Component', function () {
    it('can render for authenticated users', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Tickets');
    })->done();

    it('shows tickets for user as participant', function () {
        $user = User::factory()->create();

        $participantThread = Thread::factory()->create(['subject' => 'My Ticket']);
        $participantThread->addParticipant($user);

        $otherThread = Thread::factory()->create(['subject' => 'Other Ticket']);

        actingAs($user);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('My Ticket')
            ->assertDontSee('Other Ticket');
    })->done();

    it('shows all department tickets for staff in that department', function () {
        $chaplainStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Chaplain Ticket']);

        $engineerThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->create(['subject' => 'Engineer Ticket']);

        actingAs($chaplainStaff);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Chaplain Ticket')
            ->assertDontSee('Engineer Ticket');
    })->done();

    it('shows all tickets for Command Officers', function () {
        $commandOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Chaplain Ticket']);

        $engineerThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->create(['subject' => 'Engineer Ticket']);

        actingAs($commandOfficer);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Chaplain Ticket')
            ->assertSee('Engineer Ticket');
    })->done();

    it('shows flagged tickets across departments for Quartermaster', function () {
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        $flaggedChaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->flagged()
            ->create(['subject' => 'Flagged Chaplain Ticket']);

        $unflaggedChaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Unflagged Chaplain Ticket']);

        actingAs($quartermaster);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Flagged Chaplain Ticket')
            ->assertDontSee('Unflagged Chaplain Ticket');
    })->done();

    it('filters by open status', function () {
        $user = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $openThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create(['subject' => 'Open Ticket']);

        $closedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Closed)
            ->create(['subject' => 'Closed Ticket']);

        actingAs($user);

        Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'open')
            ->assertSee('Open Ticket')
            ->assertDontSee('Closed Ticket');
    })->done();

    it('filters by assigned to me', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $otherStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $myThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->assigned($staff)
            ->create(['subject' => 'My Assigned Ticket']);

        $otherThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->assigned($otherStaff)
            ->create(['subject' => 'Other Assigned Ticket']);

        actingAs($staff);

        Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'assigned-to-me')
            ->assertSee('My Assigned Ticket')
            ->assertDontSee('Other Assigned Ticket');
    })->done();

    it('filters by unassigned', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $otherStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $unassignedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Unassigned Ticket']);

        $assignedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->assigned($otherStaff)
            ->create(['subject' => 'Assigned Ticket']);

        actingAs($staff);

        Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'unassigned')
            ->assertSee('Unassigned Ticket')
            ->assertDontSee('Assigned Ticket');
    })->done();

    it('shows flagged filter only for Quartermaster', function () {
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        $regularStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        actingAs($quartermaster);
        $qmComponent = Volt::test('ready-room.tickets.tickets-list');
        expect($quartermaster->can('viewFlagged', Thread::class))->toBeTrue();

        actingAs($regularStaff);
        $staffComponent = Volt::test('ready-room.tickets.tickets-list');
        expect($regularStaff->can('viewFlagged', Thread::class))->toBeFalse();
    })->done();

    it('filters by flagged tickets for Quartermaster', function () {
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        $flaggedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->flagged()
            ->withOpenFlags()
            ->create(['subject' => 'Flagged Ticket']);

        $unflaggedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->create(['subject' => 'Unflagged Ticket']);

        actingAs($quartermaster);

        Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'flagged')
            ->assertSee('Flagged Ticket')
            ->assertDontSee('Unflagged Ticket');
    })->done();

    it('displays red flag badge for tickets with open flags', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $flaggedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withOpenFlags()
            ->create(['subject' => 'Flagged Ticket']);

        actingAs($staff);

        $component = Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Flagged Ticket');

        // Check that has_open_flags is true for the thread
        expect($flaggedThread->has_open_flags)->toBeTrue();
    })->done();
});
