<?php

declare(strict_types=1);

use App\Actions\DeleteCredential;
use App\Models\Credential;
use App\Models\CredentialAccessLog;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('vault', 'actions');

it('deletes the credential record', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    DeleteCredential::run($credential, $user);

    $this->assertDatabaseMissing('credentials', ['id' => $credential->id]);
});

it('removes pivot rows from credential_staff_position', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $position = StaffPosition::factory()->create();
    $credential->staffPositions()->attach($position->id);

    DeleteCredential::run($credential, $user);

    $this->assertDatabaseMissing('credential_staff_position', [
        'credential_id' => $credential->id,
        'staff_position_id' => $position->id,
    ]);
});

it('removes access logs for the credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    CredentialAccessLog::create([
        'credential_id' => $credential->id,
        'user_id' => $user->id,
        'action' => 'viewed_password',
        'created_at' => now(),
    ]);

    DeleteCredential::run($credential, $user);

    $this->assertDatabaseMissing('credential_access_logs', [
        'credential_id' => $credential->id,
    ]);
});

it('records activity on deletion', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id, 'name' => 'Gone Credential']);

    DeleteCredential::run($credential, $user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'credential_deleted',
    ]);
});
