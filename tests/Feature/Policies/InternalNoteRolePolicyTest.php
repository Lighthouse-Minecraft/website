<?php

declare(strict_types=1);

use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;

uses()->group('policies', 'internal-notes', 'roles');

// == ThreadPolicy: internalNotes == //

it('grants internalNotes to Internal Note - Manager who can view the thread', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Internal Note - Manager')
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('internalNotes', $thread))->toBeTrue();
});

it('denies internalNotes without Internal Note - Manager role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    $thread = Thread::factory()->withDepartment(StaffDepartment::Command)->create();
    $thread->participants()->create(['user_id' => $user->id]);

    expect($user->can('internalNotes', $thread))->toBeFalse();
});

it('grants internalNotes to admin', function () {
    $user = User::factory()->admin()->create();
    $thread = Thread::factory()->create();

    expect($user->can('internalNotes', $thread))->toBeTrue();
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

it('denies non-Internal-Note-Manager from viewing internal note messages', function () {
    $user = User::factory()->withRole('Ticket - User')->create();

    $thread = Thread::factory()->create();
    $thread->participants()->create(['user_id' => $user->id]);

    $message = Message::factory()->create([
        'thread_id' => $thread->id,
        'kind' => MessageKind::InternalNote,
        'user_id' => User::factory()->admin()->create()->id,
    ]);

    expect($user->can('view', $message))->toBeFalse();
});
