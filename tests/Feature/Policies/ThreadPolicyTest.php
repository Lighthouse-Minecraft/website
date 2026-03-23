<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\User;
use App\Policies\ThreadPolicy;

uses()->group('policies', 'tickets');

// === before() hook ===

it('admin bypasses thread policy via before hook except for reply and createTopic', function () {
    $admin = User::factory()->admin()->create();
    $policy = new ThreadPolicy;

    expect($policy->before($admin, 'viewAll'))->toBeTrue()
        ->and($policy->before($admin, 'reply'))->toBeNull()
        ->and($policy->before($admin, 'createTopic'))->toBeNull();
});

it('non-admin returns null from thread policy before hook', function () {
    $user = User::factory()->create();
    $policy = new ThreadPolicy;

    expect($policy->before($user, 'viewAll'))->toBeNull();
});

it('command officer returns null from thread policy before hook', function () {
    $officer = officerCommand();
    $policy = new ThreadPolicy;

    expect($policy->before($officer, 'viewAll'))->toBeNull();
});

// === viewFlagged ===

it('user with Moderator role can view flagged tickets', function () {
    $moderator = User::factory()->withRole('Moderator')->create();

    expect($moderator->can('viewFlagged', Thread::class))->toBeTrue();
});

it('Ticket - Manager can view flagged tickets', function () {
    $user = User::factory()->withRole('Ticket - Manager')->create();

    expect($user->can('viewFlagged', Thread::class))->toBeTrue();
});

it('staff without Ticket - Manager or Moderator role cannot view flagged tickets', function () {
    $crew = crewEngineer();

    expect($crew->can('viewFlagged', Thread::class))->toBeFalse();
});

it('regular user cannot view flagged tickets', function () {
    $user = User::factory()->create();

    expect($user->can('viewFlagged', Thread::class))->toBeFalse();
});

// === viewAll ===

it('admin can view all threads', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('viewAll', Thread::class))->toBeTrue();
});

it('non-admin cannot view all threads via viewAll method', function () {
    $officer = officerCommand();

    expect($officer->can('viewAll', Thread::class))->toBeFalse();
});

// === viewDepartment ===

it('user with Ticket - User role and department can view department threads', function () {
    $user = User::factory()
        ->withStaffPosition(\App\Enums\StaffDepartment::Engineer, \App\Enums\StaffRank::CrewMember)
        ->withRole('Ticket - User')
        ->create();

    expect($user->can('viewDepartment', Thread::class))->toBeTrue();
});

it('staff without Ticket - User role cannot view department threads', function () {
    $crew = crewEngineer();

    expect($crew->can('viewDepartment', Thread::class))->toBeFalse();
});

// === reply ===

it('admin can reply to unlocked threads', function () {
    $admin = User::factory()->admin()->create();
    $thread = Thread::factory()->create();

    expect($admin->can('reply', $thread))->toBeTrue();
});

it('admin cannot reply to locked threads', function () {
    $admin = User::factory()->admin()->create();
    $thread = Thread::factory()->create(['is_locked' => true]);

    expect($admin->can('reply', $thread))->toBeFalse();
});
