<?php

declare(strict_types=1);

use App\Enums\StaffRank;
use App\Models\Credential;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('vault', 'livewire');

// === vault index ===

it('allows a Vault Manager to create a credential via the index component', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $this->actingAs($user);

    livewire('vault.index')
        ->call('create', [])
        ->set('name', 'New Service')
        ->set('username', 'admin')
        ->set('password', 'secret123')
        ->call('create');

    $this->assertDatabaseHas('credentials', ['name' => 'New Service']);
});

it('rejects a non-Vault-Manager JrCrew from calling create on the vault index', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $this->actingAs($user);

    livewire('vault.index')
        ->set('name', 'Hacked')
        ->set('username', 'hacker')
        ->set('password', 'pass')
        ->call('create')
        ->assertForbidden();
});

// === vault detail ===

it('allows a Vault Manager to delete a credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    livewire('vault.detail', ['credential' => $credential])
        ->call('delete');

    $this->assertDatabaseMissing('credentials', ['id' => $credential->id]);
});

it('rejects a non-Vault-Manager from deleting a credential', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $this->actingAs($jrCrew);

    livewire('vault.detail', ['credential' => $credential])
        ->call('delete')
        ->assertForbidden();
});

it('allows a Vault Manager to update a credential', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    livewire('vault.detail', ['credential' => $credential])
        ->set('editName', 'Updated Name')
        ->set('editUsername', 'newuser')
        ->set('editPassword', '')
        ->call('saveEdit');

    expect($credential->fresh()->name)->toBe('Updated Name');
});
