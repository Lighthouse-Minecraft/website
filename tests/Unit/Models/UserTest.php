<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadStatus;
use App\Models\Thread;
use App\Models\User;

describe('User Model Ticket Methods', function () {
    it('returns false for hasActionableTickets when user has no tickets', function () {
        $user = User::factory()->create();

        expect($user->hasActionableTickets())->toBeFalse();
    })->done();

    it('returns true for hasActionableTickets when there are unassigned open tickets', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create();

        $this->actingAs($staff);

        expect($staff->hasActionableTickets())->toBeTrue();
    })->done();

    it('returns true for hasActionableTickets when assigned ticket has unread messages', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->assigned($staff)
            ->create(['last_message_at' => now()]);

        // Add staff as participant with no last_read_at (unread)
        $thread->addParticipant($staff);

        $this->actingAs($staff);

        expect($staff->hasActionableTickets())->toBeTrue();
    })->done();

    it('returns false for hasActionableTickets when assigned ticket is read', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->assigned($staff)
            ->create(['last_message_at' => now()->subHour()]);

        // Add staff as participant and mark as read
        $thread->addParticipant($staff);
        $thread->participants()->where('user_id', $staff->id)->update(['last_read_at' => now()]);

        $this->actingAs($staff);

        expect($staff->hasActionableTickets())->toBeFalse();
    })->done();

    it('returns correct openTicketsCount for user', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        // Create 3 open tickets in their department
        Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->count(3)
            ->create();

        // Create 1 closed ticket (should not count)
        Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Closed)
            ->create();

        $this->actingAs($staff);

        expect($staff->openTicketsCount())->toBe(3);
    })->done();

    it('caches hasActionableTickets result', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create();

        $this->actingAs($staff);

        // First call
        $first = $staff->hasActionableTickets();
        expect($first)->toBeTrue();

        // Delete the thread
        Thread::query()->delete();

        // Second call should still return true due to cache
        $second = $staff->hasActionableTickets();
        expect($second)->toBeTrue();

        // Clear cache and check again
        \Illuminate\Support\Facades\Cache::forget("user.{$staff->id}.actionable_tickets");
        $third = $staff->hasActionableTickets();
        expect($third)->toBeFalse();
    })->done();

    it('caches openTicketsCount result', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->count(2)
            ->create();

        $this->actingAs($staff);

        // First call
        $first = $staff->openTicketsCount();
        expect($first)->toBe(2);

        // Add another ticket
        Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->withStatus(ThreadStatus::Open)
            ->create();

        // Second call should still return 2 due to cache
        $second = $staff->openTicketsCount();
        expect($second)->toBe(2);

        // Clear cache and check again
        \Illuminate\Support\Facades\Cache::forget("user.{$staff->id}.open_tickets_count");
        $third = $staff->openTicketsCount();
        expect($third)->toBe(3);
    })->done();
});
