<?php

declare(strict_types=1);

use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('applications', 'policies');

it('allows admin to review applications', function () {
    $admin = loginAsAdmin();

    expect($admin->can('viewAny', StaffApplication::class))->toBeTrue();
});

it('command officer can view applications via officer rank check', function () {
    $officer = officerCommand();

    expect($officer->can('viewAny', StaffApplication::class))->toBeTrue();
});

it('allows crew member from any department to view applications', function () {
    $crew = crewEngineer();

    expect($crew->can('viewAny', StaffApplication::class))->toBeTrue();
});

it('denies jr crew from viewing applications', function () {
    $jrCrew = jrCrewEngineer();

    expect($jrCrew->can('viewAny', StaffApplication::class))->toBeFalse();
});

it('allows user to view own application', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create();
    $application = StaffApplication::factory()->create([
        'user_id' => $user->id,
        'staff_position_id' => $position->id,
    ]);

    expect($user->can('view', $application))->toBeTrue();
});

it('denies user from viewing others application', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create();
    $application = StaffApplication::factory()->create([
        'user_id' => $otherUser->id,
        'staff_position_id' => $position->id,
    ]);

    expect($user->can('view', $application))->toBeFalse();
});

it('allows non-brig user to create applications', function () {
    $user = User::factory()->create();

    expect($user->can('create', StaffApplication::class))->toBeTrue();
});

it('denies user in brig from creating applications', function () {
    $user = User::factory()->create(['in_brig' => true]);

    expect($user->can('create', StaffApplication::class))->toBeFalse();
});
