<?php

declare(strict_types=1);

use App\Actions\AssignCredentialPositions;
use App\Models\Credential;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('vault', 'actions');

it('assigns positions to a credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $position1 = StaffPosition::factory()->create();
    $position2 = StaffPosition::factory()->create();

    AssignCredentialPositions::run($credential, $user, [$position1->id, $position2->id]);

    $this->assertDatabaseHas('credential_staff_position', [
        'credential_id' => $credential->id,
        'staff_position_id' => $position1->id,
    ]);
    $this->assertDatabaseHas('credential_staff_position', [
        'credential_id' => $credential->id,
        'staff_position_id' => $position2->id,
    ]);
});

it('removes positions no longer in the sync list', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $position1 = StaffPosition::factory()->create();
    $position2 = StaffPosition::factory()->create();

    $credential->staffPositions()->attach([$position1->id, $position2->id]);

    AssignCredentialPositions::run($credential, $user, [$position1->id]);

    $this->assertDatabaseHas('credential_staff_position', [
        'credential_id' => $credential->id,
        'staff_position_id' => $position1->id,
    ]);
    $this->assertDatabaseMissing('credential_staff_position', [
        'credential_id' => $credential->id,
        'staff_position_id' => $position2->id,
    ]);
});

it('clears all positions when synced with an empty array', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $position = StaffPosition::factory()->create();
    $credential->staffPositions()->attach($position->id);

    AssignCredentialPositions::run($credential, $user, []);

    $this->assertDatabaseMissing('credential_staff_position', [
        'credential_id' => $credential->id,
    ]);
});

it('records activity on position assignment', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $position = StaffPosition::factory()->create();

    AssignCredentialPositions::run($credential, $user, [$position->id]);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => Credential::class,
        'subject_id' => $credential->id,
        'action' => 'credential_positions_assigned',
    ]);
});
