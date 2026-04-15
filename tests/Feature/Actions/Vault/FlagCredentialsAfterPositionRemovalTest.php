<?php

declare(strict_types=1);

use App\Actions\CreateCredential;
use App\Actions\FlagCredentialsAfterPositionRemoval;
use App\Models\CredentialAccessLog;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('vault', 'actions');

it('flags credentials that the departing user accessed', function () {
    $manager = User::factory()->withRole('Vault Manager')->create();
    $departing = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($departing->id)->create();

    $credential = CreateCredential::run($manager, [
        'name' => 'Accessed Credential',
        'username' => 'admin',
        'password' => 'secret',
    ]);
    $credential->staffPositions()->attach($position->id);

    // Simulate the departing user having accessed this credential
    CredentialAccessLog::create([
        'credential_id' => $credential->id,
        'user_id' => $departing->id,
        'action' => 'viewed_password',
        'created_at' => now(),
    ]);

    FlagCredentialsAfterPositionRemoval::run($departing, $position);

    expect($credential->fresh()->needs_password_change)->toBeTrue();
});

it('does not flag credentials the departing user never accessed', function () {
    $manager = User::factory()->withRole('Vault Manager')->create();
    $departing = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($departing->id)->create();

    $credential = CreateCredential::run($manager, [
        'name' => 'Unaccessed Credential',
        'username' => 'admin',
        'password' => 'secret',
    ]);
    $credential->staffPositions()->attach($position->id);

    // No access log for the departing user

    FlagCredentialsAfterPositionRemoval::run($departing, $position);

    expect($credential->fresh()->needs_password_change)->toBeFalse();
});

it('does not flag credentials accessed only by other users', function () {
    $manager = User::factory()->withRole('Vault Manager')->create();
    $departing = User::factory()->create();
    $otherUser = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($departing->id)->create();

    $credential = CreateCredential::run($manager, [
        'name' => 'Other User Credential',
        'username' => 'admin',
        'password' => 'secret',
    ]);
    $credential->staffPositions()->attach($position->id);

    // Only the OTHER user accessed this credential
    CredentialAccessLog::create([
        'credential_id' => $credential->id,
        'user_id' => $otherUser->id,
        'action' => 'viewed_password',
        'created_at' => now(),
    ]);

    FlagCredentialsAfterPositionRemoval::run($departing, $position);

    expect($credential->fresh()->needs_password_change)->toBeFalse();
});

it('does not flag credentials not assigned to the departing position', function () {
    $manager = User::factory()->withRole('Vault Manager')->create();
    $departing = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($departing->id)->create();
    $otherPosition = StaffPosition::factory()->create();

    // Credential is on a DIFFERENT position
    $credential = CreateCredential::run($manager, [
        'name' => 'Different Position Credential',
        'username' => 'admin',
        'password' => 'secret',
    ]);
    $credential->staffPositions()->attach($otherPosition->id);

    CredentialAccessLog::create([
        'credential_id' => $credential->id,
        'user_id' => $departing->id,
        'action' => 'viewed_password',
        'created_at' => now(),
    ]);

    FlagCredentialsAfterPositionRemoval::run($departing, $position);

    expect($credential->fresh()->needs_password_change)->toBeFalse();
});
