<?php

declare(strict_types=1);

use App\Models\Credential;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('background-checks', 'livewire', 'vault');

it('shows credential names for a user with a vault-linked staff position', function () {
    $viewer = officerCommand();
    $target = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($target->id)->create();

    $credential = Credential::factory()->create(['name' => 'Server Hosting Panel', 'created_by' => $viewer->id]);
    $credential->staffPositions()->attach($position->id);

    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSeeLivewire('users.vault-keys-card')
        ->assertSee('Server Hosting Panel');
});

it('shows no vault access message when staff position has no credentials', function () {
    $viewer = officerCommand();
    $target = User::factory()->create();
    StaffPosition::factory()->assignedTo($target->id)->create();

    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSeeLivewire('users.vault-keys-card')
        ->assertSee('No vault access assigned');
});

it('shows no vault access message when user has no staff position', function () {
    $viewer = officerCommand();
    $target = User::factory()->create();

    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSeeLivewire('users.vault-keys-card')
        ->assertSee('No vault access assigned');
});

it('hides vault-keys-card from users who cannot view-vault', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertDontSeeLivewire('users.vault-keys-card');
});

it('does not expose credential secrets in the rendered output', function () {
    $viewer = officerCommand();
    $target = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($target->id)->create();

    $credential = Credential::factory()->create([
        'name' => 'Secret Server',
        'username' => 'admin',
        'password' => 'super-secret-password',
        'created_by' => $viewer->id,
    ]);
    $credential->staffPositions()->attach($position->id);

    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSee('Secret Server')
        ->assertDontSee('super-secret-password')
        ->assertDontSee('admin');
});
