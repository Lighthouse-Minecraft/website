<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Thread;
use App\Models\User;

use function Pest\Laravel\actingAs;

describe('Thread Authorization', function () {
    it('allows Command Officers to view all threads', function () {
        $commandOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
            ->create();

        $thread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($commandOfficer);

        expect($commandOfficer->can('viewAll', Thread::class))->toBeTrue()
            ->and($thread->isVisibleTo($commandOfficer))->toBeTrue();
    })->done();

    it('allows staff to view threads in their department', function () {
        $chaplainStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
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

    it('allows Quartermaster to view flagged threads across departments', function () {
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
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

    it('allows staff to change thread status in their department', function () {
        $chaplainStaff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
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

    it('allows officers to assign threads in their department', function () {
        $chaplainOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($chaplainOfficer);

        expect($chaplainOfficer->can('assign', $chaplainThread))->toBeTrue();
    })->done();

    it('prevents non-officers from assigning threads', function () {
        $chaplainMember = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
            ->create();

        $chaplainThread = Thread::factory()
            ->withDepartment(StaffDepartment::Chaplain)
            ->create();

        actingAs($chaplainMember);

        expect($chaplainMember->can('assign', $chaplainThread))->toBeFalse();
    })->done();

    it('allows officers to reroute threads in their department', function () {
        $chaplainOfficer = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
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
