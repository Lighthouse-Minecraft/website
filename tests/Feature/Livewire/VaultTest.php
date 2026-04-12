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

it('rejects a position holder (non-Vault-Manager) from deleting a credential', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    // Give the jrCrew user a position and assign it to the credential so they can view it
    $jrCrew = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    $position = \App\Models\StaffPosition::factory()->assignedTo($jrCrew->id)->create();
    $credential->staffPositions()->attach($position->id);
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

// === password reveal ===

it('reveals the password after successful re-authentication', function () {
    $user = User::factory()->withRole('Vault Manager')->create([
        'staff_rank' => StaffRank::JrCrew,
        'password' => bcrypt('my-vault-password'),
    ]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    livewire('vault.detail', ['credential' => $credential])
        ->set('reauthPassword', 'my-vault-password')
        ->call('reauth')
        ->assertSet('revealedPassword', $credential->fresh()->password);
});

it('does not reveal the password with a wrong re-auth password', function () {
    $user = User::factory()->withRole('Vault Manager')->create([
        'staff_rank' => StaffRank::JrCrew,
        'password' => bcrypt('correct-password'),
    ]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    livewire('vault.detail', ['credential' => $credential])
        ->set('reauthPassword', 'wrong-password')
        ->call('reauth')
        ->assertSet('revealedPassword', null)
        ->assertSet('reauthError', 'Incorrect password. Please try again.');
});

it('reveals the password immediately when the vault session is already unlocked', function () {
    $user = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    // Unlock the session before mounting the component
    session(['vault_unlocked_at' => now()->timestamp]);

    livewire('vault.detail', ['credential' => $credential])
        ->call('revealPassword')
        ->assertSet('revealedPassword', $credential->fresh()->password);
});

it('enforces policy on reveal: position holder can reveal their credential password', function () {
    $manager = User::factory()->withRole('Vault Manager')->create(['staff_rank' => StaffRank::JrCrew]);
    $credential = Credential::factory()->create(['created_by' => $manager->id]);

    $jrCrew = User::factory()->create([
        'staff_rank' => StaffRank::JrCrew,
        'password' => bcrypt('staff-pass'),
    ]);
    $position = \App\Models\StaffPosition::factory()->assignedTo($jrCrew->id)->create();
    $credential->staffPositions()->attach($position->id);
    $this->actingAs($jrCrew);

    livewire('vault.detail', ['credential' => $credential])
        ->set('reauthPassword', 'staff-pass')
        ->call('reauth')
        ->assertSet('revealedPassword', $credential->fresh()->password);
});

// === TOTP display ===

it('shows the TOTP code after successful re-authentication', function () {
    $user = User::factory()->withRole('Vault Manager')->create([
        'staff_rank' => StaffRank::JrCrew,
        'password' => bcrypt('vault-pass'),
    ]);
    $credential = Credential::factory()->withTotp()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    livewire('vault.detail', ['credential' => $credential])
        ->set('reauthPurpose', 'totp')
        ->set('reauthPassword', 'vault-pass')
        ->call('reauth')
        ->assertSet('totpCode', fn ($code) => (bool) preg_match('/^\d{6}$/', $code))
        ->assertSet('revealedPassword', null); // should not reveal password when purpose is totp
});

it('always requires re-auth to show TOTP code even when vault session is unlocked', function () {
    $user = User::factory()->withRole('Vault Manager')->create([
        'staff_rank' => StaffRank::JrCrew,
        'password' => bcrypt('vault-pass'),
    ]);
    $credential = Credential::factory()->withTotp()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    // Unlock the vault session
    session(['vault_unlocked_at' => now()->timestamp]);

    // showTotp() should set reauthPurpose to 'totp' without showing the code directly
    livewire('vault.detail', ['credential' => $credential])
        ->call('showTotp')
        ->assertSet('totpCode', null)
        ->assertSet('reauthPurpose', 'totp');
});

it('refreshes the TOTP code without re-auth', function () {
    $user = User::factory()->withRole('Vault Manager')->create([
        'staff_rank' => StaffRank::JrCrew,
        'password' => bcrypt('vault-pass'),
    ]);
    $credential = Credential::factory()->withTotp()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    $component = livewire('vault.detail', ['credential' => $credential])
        ->set('reauthPurpose', 'totp')
        ->set('reauthPassword', 'vault-pass')
        ->call('reauth');

    $firstCode = $component->get('totpCode');

    // refreshTotp can be called without re-auth
    $component->call('refreshTotp');

    // The code may or may not change (same 30-second window), but it should still be a 6-digit string
    expect($component->get('totpCode'))->toMatch('/^\d{6}$/');
});
