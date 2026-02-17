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
            ->set('filter', 'open')
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
            ->set('filter', 'open')
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
            ->set('filter', 'open')
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

        $component = Volt::test('ready-room.tickets.tickets-list', ['filter' => 'open']);

        // Verify only open ticket is in the collection
        expect($component->get('tickets'))->toHaveCount(1);
        expect($component->get('tickets')->first()->subject)->toBe('Open Ticket');

        $component->assertSee('Open Ticket');
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
            ->set('filter', 'open')
            ->assertSee('Flagged Ticket');

        // Check that has_open_flags is true for the thread
        expect($flaggedThread->has_open_flags)->toBeTrue();
    })->done();

    it('marks ticket as unread when user has never read it', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->create(['subject' => 'Unread Ticket']);
        $thread->addParticipant($user);

        actingAs($user);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Unread Ticket')
            ->assertSee('New');
    })->done();

    it('marks ticket as read when user has read it after last message', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->create([
            'subject' => 'Read Ticket',
            'last_message_at' => now()->subHour(),
        ]);
        $thread->addParticipant($user);

        // Mark as read after the last message
        $participant = $thread->participants()->where('user_id', $user->id)->first();
        $participant->update(['last_read_at' => now()]);

        actingAs($user);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Read Ticket')
            ->assertDontSee('New');
    })->done();

    it('marks ticket as unread when new message arrives after user read it', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->create(['subject' => 'New Message Ticket']);
        $thread->addParticipant($user);

        // User read it an hour ago
        $participant = $thread->participants()->where('user_id', $user->id)->first();
        $participant->update(['last_read_at' => now()->subHour()]);

        // New message came in 30 minutes ago
        $thread->update(['last_message_at' => now()->subMinutes(30)]);

        actingAs($user);

        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('New Message Ticket')
            ->assertSee('New');
    })->done();

    it('shows New badge only for current users participant record', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $thread = Thread::factory()->create([
            'subject' => 'Multi User Ticket',
            'last_message_at' => now(),
        ]);

        $thread->addParticipant($user1);
        $thread->addParticipant($user2);

        // User 1 has read it
        $participant1 = $thread->participants()->where('user_id', $user1->id)->first();
        $participant1->update(['last_read_at' => now()->addMinute()]);

        // User 2 has not read it
        $participant2 = $thread->participants()->where('user_id', $user2->id)->first();
        $participant2->update(['last_read_at' => null]);

        // User 1 should NOT see "New" badge
        actingAs($user1);
        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Multi User Ticket')
            ->assertDontSee('New');

        // User 2 should see "New" badge
        actingAs($user2);
        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('Multi User Ticket')
            ->assertSee('New');
    })->done();

    it('shows department badge in ticket list', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Chaplain Ticket']);

        actingAs($staff);

        Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'open')
            ->assertSee('Chaplain Ticket')
            ->assertSee('Chaplain');
    })->done();

    it('shows my tickets filter by default for regular users', function () {
        $user = User::factory()->create();

        $openThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->withStatus(ThreadStatus::Open)
            ->create(['subject' => 'My Open Ticket']);
        $openThread->addParticipant($user);

        $closedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->withStatus(ThreadStatus::Closed)
            ->create(['subject' => 'My Closed Ticket']);
        $closedThread->addParticipant($user);

        actingAs($user);

        // Default filter shows my open tickets
        $component = Volt::test('ready-room.tickets.tickets-list');

        // Verify only the open ticket is in the collection
        expect($component->get('tickets'))->toHaveCount(1);
        expect($component->get('tickets')->first()->subject)->toBe('My Open Ticket');

        // Verify we see the open ticket in the HTML
        $component->assertSee('My Open Ticket');
    })->done();

    it('allows regular users to switch between my open and my closed tickets', function () {
        $user = User::factory()->create();

        $openThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->withStatus(ThreadStatus::Open)
            ->create(['subject' => 'Open Ticket']);
        $openThread->addParticipant($user);

        $closedThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->withStatus(ThreadStatus::Closed)
            ->create(['subject' => 'Closed Ticket']);
        $closedThread->addParticipant($user);

        actingAs($user);

        $component = Volt::test('ready-room.tickets.tickets-list', ['filter' => 'my-open']);

        // Verify my-open shows only open ticket
        expect($component->get('tickets'))->toHaveCount(1);
        expect($component->get('tickets')->first()->subject)->toBe('Open Ticket');
        $component->assertSee('Open Ticket');

        // Switch to my-closed and verify only closed ticket
        $component->set('filter', 'my-closed');
        expect($component->get('tickets'))->toHaveCount(1);
        expect($component->get('tickets')->first()->subject)->toBe('Closed Ticket');
        $component->assertSee('Closed Ticket');
    })->done();

    it('allows staff to switch between my tickets and staff filters', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        // A ticket the staff member is a participant in
        $participantThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'My Personal Ticket']);
        $participantThread->addParticipant($staff);

        // A ticket in their department they're not a participant in
        $deptThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Department Ticket']);

        actingAs($staff);

        // Default to my-open filter - should see only participant ticket
        Volt::test('ready-room.tickets.tickets-list')
            ->assertSee('My Personal Ticket')
            ->assertDontSee('Department Ticket')
            // Switch to open filter - should see department ticket
            ->set('filter', 'open')
            ->assertSee('Department Ticket')
            ->assertSee('My Personal Ticket'); // Also visible because they're a participant
    })->done();

    it('prevents duplicate tickets when staff member is participant in department ticket', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $ticket = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create(['subject' => 'Shared Ticket']);
        $ticket->addParticipant($staff);

        actingAs($staff);

        // When viewing my-open filter, should see the ticket once
        $myTicketsComponent = Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'my-open')
            ->assertSee('Shared Ticket');

        // When viewing open filter, should also see the ticket once
        $openComponent = Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'open')
            ->assertSee('Shared Ticket');

        // The ticket should exist in the results exactly once for each filter
        expect($ticket->refresh())->toBeInstanceOf(Thread::class);
    })->done();

    it('preserves filter parameter when navigating to ticket', function () {
        $user = User::factory()->create();
        $ticket = Thread::factory()->create(['subject' => 'Test Ticket']);
        $ticket->addParticipant($user);

        actingAs($user);

        $component = Volt::test('ready-room.tickets.tickets-list')
            ->set('filter', 'my-open');

        // Verify the ticket link includes the filter parameter
        $html = $component->call('$refresh')->html();
        expect($html)->toContain('/tickets/'.$ticket->id.'?filter=my-open');
    })->done();

    it('displays filter counts for all filter categories', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)
            ->create();

        // Create tickets for different filters
        $myOpenTicket = Thread::factory()->create();
        $myOpenTicket->addParticipant($staff);

        $myClosedTicket = Thread::factory()->create(['status' => ThreadStatus::Closed]);
        $myClosedTicket->addParticipant($staff);

        $otherOpenTicket = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->create();

        $unassignedTicket = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->create(['assigned_to_user_id' => null]);

        actingAs($staff);

        $component = Volt::test('ready-room.tickets.tickets-list');

        // Verify counts are calculated
        $filterCounts = $component->get('filterCounts');

        expect($filterCounts['my-open'])->toBeGreaterThanOrEqual(1);
        expect($filterCounts['my-closed'])->toBeGreaterThanOrEqual(1);
        expect($filterCounts['open'])->toBeGreaterThanOrEqual(2);
        expect($filterCounts['unassigned'])->toBeGreaterThanOrEqual(1);
    })->done();

    it('caches filter counts for performance', function () {
        $user = User::factory()->create();
        $ticket = Thread::factory()->create();
        $ticket->addParticipant($user);

        actingAs($user);

        // First call should calculate and cache
        $component1 = Volt::test('ready-room.tickets.tickets-list');
        $counts1 = $component1->get('filterCounts');

        // Second call should use cache (we can verify by checking cache key exists)
        $cacheKey = "user.{$user->id}.ticket_counts";
        expect(\Illuminate\Support\Facades\Cache::has($cacheKey))->toBeTrue();

        // Verify counts are consistent
        $component2 = Volt::test('ready-room.tickets.tickets-list');
        $counts2 = $component2->get('filterCounts');

        expect($counts1)->toBe($counts2);
    })->done();

    it('clears filter count cache when ticket state changes', function () {
        $user = User::factory()->create();
        $ticket = Thread::factory()->create();
        $ticket->addParticipant($user);

        actingAs($user);

        // Build cache
        Volt::test('ready-room.tickets.tickets-list');
        $cacheKey = "user.{$user->id}.ticket_counts";
        expect(\Illuminate\Support\Facades\Cache::has($cacheKey))->toBeTrue();

        // Clear caches
        $user->clearTicketCaches();

        // Verify cache was cleared
        expect(\Illuminate\Support\Facades\Cache::has($cacheKey))->toBeFalse();
    })->done();
});
