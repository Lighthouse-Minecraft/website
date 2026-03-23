<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Thread;
use App\Models\User;

use function Pest\Laravel\actingAs;

describe('Thread Authorization', function () {
    it('command officer without admin no longer bypasses viewAll', function () {
        $commandOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
            ->withRole('Ticket - User')
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($commandOfficer);

        expect($commandOfficer->can('viewAll', Thread::class))->toBeFalse();
    })->done();

    it('allows admin to view all threads', function () {
        $admin = User::factory()->admin()->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($admin);

        expect($admin->can('viewAll', Thread::class))->toBeTrue()
            ->and($thread->isVisibleTo($admin))->toBeTrue();
    })->done();

    it('allows staff with Ticket - User role to view threads in their department', function () {
        $chaplainStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->withRole('Ticket - User')
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        $engineerThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->create();

        actingAs($chaplainStaff);

        expect($chaplainThread->isVisibleTo($chaplainStaff))->toBeTrue()
            ->and($engineerThread->isVisibleTo($chaplainStaff))->toBeFalse();
    })->done();

    it('allows Ticket - Manager to view flagged threads across departments', function () {
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->withRole('Ticket - Manager')
            ->create();

        $flaggedChaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->flagged()
            ->create();

        $unflaggedChaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($quartermaster);

        expect($quartermaster->can('viewFlagged', Thread::class))->toBeTrue()
            ->and($flaggedChaplainThread->isVisibleTo($quartermaster))->toBeTrue()
            ->and($unflaggedChaplainThread->isVisibleTo($quartermaster))->toBeFalse();
    })->done();

    it('allows participants to view threads they are part of', function () {
        $user = User::factory()->create();

        $participantThread = Thread::factory()->create();
        $participantThread->addParticipant($user);

        $nonParticipantThread = Thread::factory()->create();

        actingAs($user);

        expect($participantThread->isVisibleTo($user))->toBeTrue()
            ->and($nonParticipantThread->isVisibleTo($user))->toBeFalse();
    })->done();

    it('allows staff with Ticket - User role to change thread status in their department', function () {
        $chaplainStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->withRole('Ticket - User')
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        $engineerThread = Thread::factory()
            ->withDepartment(StaffDepartment::Engineer)
            ->create();

        actingAs($chaplainStaff);

        expect($chaplainStaff->can('changeStatus', $chaplainThread))->toBeTrue()
            ->and($chaplainStaff->can('changeStatus', $engineerThread))->toBeFalse();
    })->done();

    it('allows Ticket - Manager to assign threads in their department', function () {
        $chaplainOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->withRole('Ticket - User')
            ->withRole('Ticket - Manager')
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($chaplainOfficer);

        expect($chaplainOfficer->can('assign', $chaplainThread))->toBeTrue();
    })->done();

    it('prevents staff without Ticket - Manager from assigning threads', function () {
        $chaplainMember = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->withRole('Ticket - User')
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($chaplainMember);

        expect($chaplainMember->can('assign', $chaplainThread))->toBeFalse();
    })->done();

    it('allows Ticket - Manager to reroute threads in their department', function () {
        $chaplainOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->withRole('Ticket - User')
            ->withRole('Ticket - Manager')
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($chaplainOfficer);

        expect($chaplainOfficer->can('reroute', $chaplainThread))->toBeTrue();
    })->done();

    it('allows Admin to do everything', function () {
        $admin = User::factory()->admin()->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($admin);

        expect($admin->can('viewAll', Thread::class))->toBeTrue()
            ->and($admin->can('changeStatus', $thread))->toBeTrue()
            ->and($admin->can('assign', $thread))->toBeTrue()
            ->and($admin->can('reroute', $thread))->toBeTrue();
    })->done();
});
