<?php

declare(strict_types=1);

use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;

uses()->group('policies', 'tickets', 'roles');

// == viewDepartment == //

it('grants viewDepartment to user with Ticket - User role and a department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    expect($user->can('viewDepartment', Thread::class))->toBeTrue();
});

it('denies viewDepartment to Ticket - Manager (they use viewAll instead)', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
        ->withRole('Ticket - Manager')
        ->create();

    expect($user->can('viewDepartment', Thread::class))->toBeFalse();
});

it('denies viewDepartment without Ticket - User role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    expect($user->can('viewDepartment', Thread::class))->toBeFalse();
});

// == viewAll == //

it('grants viewAll to Ticket - Manager', function () {
    $user = User::factory()->withRole('Ticket - Manager')->create();

    expect($user->can('viewAll', Thread::class))->toBeTrue();
});

it('denies viewAll to Ticket - User without Ticket - Manager role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    expect($user->can('viewAll', Thread::class))->toBeFalse();
});

it('grants viewDepartment to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('viewDepartment', Thread::class))->toBeTrue();
});

// == createAsStaff == //

it('grants createAsStaff to user with Ticket - User role', function () {
    $user = User::factory()->withRole('Ticket - User')->create();

    expect($user->can('createAsStaff', Thread::class))->toBeTrue();
});

it('denies createAsStaff without Ticket - User role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->create();

    expect($user->can('createAsStaff', Thread::class))->toBeFalse();
});

// == internalNotes == //

it('grants internalNotes to Internal Note - Manager who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->withRole('Internal Note - Manager')
        ->create();

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('internalNotes', $thread))->toBeTrue();
});

it('denies internalNotes without Internal Note - Manager role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('internalNotes', $thread))->toBeFalse();
});

// == changeStatus == //

it('grants changeStatus to Ticket - User who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('changeStatus', $thread))->toBeTrue();
});

it('denies changeStatus without Ticket - User role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('changeStatus', $thread))->toBeFalse();
});

// == assign == //

it('grants assign to Ticket - Manager who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Ticket - Manager')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('assign', $thread))->toBeTrue();
});

it('denies assign with only Ticket - User role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('assign', $thread))->toBeFalse();
});

it('grants assign to admin', function () {
    $user = User::factory()->admin()->create();
    $thread = Thread::factory()->create();

    expect($user->can('assign', $thread))->toBeTrue();
});

// == reroute == //

it('grants reroute to Ticket - Manager who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Ticket - Manager')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('reroute', $thread))->toBeTrue();
});

it('denies reroute with only Ticket - User role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('reroute', $thread))->toBeFalse();
});

// == close == //

it('grants close to Ticket - User who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('close', $thread))->toBeTrue();
});

it('grants close to ticket creator without Ticket - User role', function () {
    $user = User::factory()->create();
    $thread = Thread::factory()->create(['created_by_user_id' => $user->id]);

    expect($user->can('close', $thread))->toBeTrue();
});

it('denies close to non-creator without Ticket - User role', function () {
    $user = User::factory()->create();
    $thread = Thread::factory()->create();

    expect($user->can('close', $thread))->toBeFalse();
});

// == viewFlagged == //

it('grants viewFlagged to Ticket - Manager', function () {
    $user = User::factory()->withRole('Ticket - Manager')->create();

    expect($user->can('viewFlagged', Thread::class))->toBeTrue();
});

it('grants viewFlagged to Moderator', function () {
    $user = User::factory()->withRole('Moderator')->create();

    expect($user->can('viewFlagged', Thread::class))->toBeTrue();
});

it('denies viewFlagged without Ticket - Manager or Moderator role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    expect($user->can('viewFlagged', Thread::class))->toBeFalse();
});

// == addParticipant == //

it('grants addParticipant to Ticket - User who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('addParticipant', $thread))->toBeTrue();
});

it('grants addParticipant to Ticket - Manager who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
        ->withRole('Ticket - Manager')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();

    expect($user->can('addParticipant', $thread))->toBeTrue();
});

it('denies addParticipant without Ticket - User or Ticket - Manager role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('addParticipant', $thread))->toBeFalse();
});

// == MessagePolicy: view internal notes == //

it('allows Internal Note - Manager to view internal note messages', function () {
    $user = User::factory()->withRole('Internal Note - Manager')->create();

    $thread = Thread::factory()->create();
    $thread->participants()->create(['user_id' => $user->id]);

    $message = Message::factory()->create([
        'thread_id' => $thread->id,
        'kind' => MessageKind::InternalNote,
        'user_id' => User::factory()->admin()->create()->id,
    ]);

    expect($user->can('view', $message))->toBeTrue();
});

it('denies non-Ticket-User from viewing internal note messages', function () {
    $user = User::factory()->create();

    $thread = Thread::factory()->create();
    $thread->participants()->create(['user_id' => $user->id]);

    $message = Message::factory()->create([
        'thread_id' => $thread->id,
        'kind' => MessageKind::InternalNote,
        'user_id' => User::factory()->admin()->create()->id,
    ]);

    expect($user->can('view', $message))->toBeFalse();
});
