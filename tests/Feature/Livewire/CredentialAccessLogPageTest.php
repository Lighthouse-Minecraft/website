<?php

declare(strict_types=1);

use App\Actions\CreateCredential;
use App\Actions\RecordCredentialAccess;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('vault', 'livewire');

it('renders for a user with the Logs - Viewer role', function () {
    $viewer = User::factory()->withRole('Logs - Viewer')->create();
    $this->actingAs($viewer);

    livewire('admin-manage-credential-access-log-page')
        ->assertOk();
});

it('is forbidden to users without the Logs - Viewer role', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire('admin-manage-credential-access-log-page')
        ->assertForbidden();
});

it('displays log entries in the table', function () {
    $manager = User::factory()->withRole('Vault Manager')->create();
    $credential = CreateCredential::run($manager, [
        'name' => 'Logged Credential',
        'username' => 'admin',
        'password' => 'pass',
    ]);

    RecordCredentialAccess::run($credential, $manager, 'viewed_password');

    $viewer = User::factory()->withRole('Logs - Viewer')->create();
    $this->actingAs($viewer);

    livewire('admin-manage-credential-access-log-page')
        ->assertSee('Logged Credential')
        ->assertSee('Viewed Password');
});
