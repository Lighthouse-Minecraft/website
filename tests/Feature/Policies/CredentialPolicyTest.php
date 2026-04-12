<?php

declare(strict_types=1);

use App\Enums\StaffRank;
use App\Models\Credential;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('vault', 'policies');

// === Vault Manager access ===

it('allows Vault Manager to view any credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    expect($user->can('view', $credential))->toBeTrue();
});

it('allows Vault Manager to update any credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    expect($user->can('update', $credential))->toBeTrue();
});

it('allows Vault Manager to delete any credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    expect($user->can('delete', $credential))->toBeTrue();
});

it('allows Vault Manager to manage positions on any credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    expect($user->can('managePositions', $credential))->toBeTrue();
});

// === Position holder access ===

it('allows position holder to view a credential their position is assigned to', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $position = StaffPosition::factory()->assignedTo($jrCrew->id)->create();
    $credential->staffPositions()->attach($position->id);

    expect($jrCrew->can('view', $credential))->toBeTrue();
});

it('allows position holder to update a credential their position is assigned to', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $position = StaffPosition::factory()->assignedTo($jrCrew->id)->create();
    $credential->staffPositions()->attach($position->id);

    expect($jrCrew->can('update', $credential))->toBeTrue();
});

it('denies position holder from deleting a credential', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $position = StaffPosition::factory()->assignedTo($jrCrew->id)->create();
    $credential->staffPositions()->attach($position->id);

    expect($jrCrew->can('delete', $credential))->toBeFalse();
});

it('denies position holder from managing positions on a credential', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $position = StaffPosition::factory()->assignedTo($jrCrew->id)->create();
    $credential->staffPositions()->attach($position->id);

    expect($jrCrew->can('managePositions', $credential))->toBeFalse();
});

// === No position access ===

it('denies staff member with no assigned position from viewing an unassigned credential', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);

    expect($jrCrew->can('view', $credential))->toBeFalse();
});

it('denies staff member whose position is not assigned from viewing a credential', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $otherPosition = StaffPosition::factory()->assignedTo($jrCrew->id)->create();
    // credential is assigned to a DIFFERENT position
    $assignedPosition = StaffPosition::factory()->create();
    $credential->staffPositions()->attach($assignedPosition->id);

    expect($jrCrew->can('view', $credential))->toBeFalse();
});
