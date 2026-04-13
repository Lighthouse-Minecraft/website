<?php

declare(strict_types=1);

use App\Actions\AssignCredentialPositions;
use App\Actions\CreateCredential;
use App\Actions\DeleteCredential;
use App\Actions\RecordCredentialAccess;
use App\Actions\UpdateCredential;
use App\Models\Credential;
use App\Models\User;

uses()->group('vault', 'actions');

it('records a credential access log entry', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = CreateCredential::run($user, [
        'name' => 'Test',
        'username' => 'admin',
        'password' => 'pass',
    ]);

    RecordCredentialAccess::run($credential, $user, 'viewed_password');

    $this->assertDatabaseHas('credential_access_logs', [
        'credential_id' => $credential->id,
        'user_id' => $user->id,
        'action' => 'viewed_password',
    ]);
});

it('CreateCredential logs a created access entry', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Create Log Test',
        'username' => 'admin',
        'password' => 'pass',
    ]);

    $this->assertDatabaseHas('credential_access_logs', [
        'credential_id' => $credential->id,
        'user_id' => $user->id,
        'action' => 'created',
    ]);
});

it('UpdateCredential logs an updated access entry', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = CreateCredential::run($user, [
        'name' => 'Update Log Test',
        'username' => 'admin',
        'password' => 'pass',
    ]);

    UpdateCredential::run($credential, $user, ['name' => 'Updated Name']);

    $this->assertDatabaseHas('credential_access_logs', [
        'credential_id' => $credential->id,
        'user_id' => $user->id,
        'action' => 'updated',
    ]);
});

it('DeleteCredential logs a deleted access entry that persists after deletion', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = CreateCredential::run($user, [
        'name' => 'Delete Log Test',
        'username' => 'admin',
        'password' => 'pass',
    ]);
    $credentialId = $credential->id;

    DeleteCredential::run($credential, $user);

    // The credential is gone
    $this->assertDatabaseMissing('credentials', ['id' => $credentialId]);

    // The 'deleted' audit entry is preserved with credential_id nullified
    $this->assertDatabaseHas('credential_access_logs', [
        'credential_id' => null,
        'user_id' => $user->id,
        'action' => 'deleted',
    ]);
});

it('AssignCredentialPositions logs a positions_assigned access entry', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = CreateCredential::run($user, [
        'name' => 'Assign Log Test',
        'username' => 'admin',
        'password' => 'pass',
    ]);

    AssignCredentialPositions::run($credential, $user, []);

    $this->assertDatabaseHas('credential_access_logs', [
        'credential_id' => $credential->id,
        'user_id' => $user->id,
        'action' => 'positions_assigned',
    ]);
});
