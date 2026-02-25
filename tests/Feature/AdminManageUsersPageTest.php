<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin can view the manage users page', function () {
    $response = $this->actingAs($this->admin)
        ->get('/acp');

    $response->assertSuccessful()
        ->assertSeeLivewire('admin-control-panel-tabs');
});

test('pagination works correctly with many users', function () {
    // Create 12 users so we'll have more than 5 per page
    User::factory()->count(12)->create();

    $this->actingAs($this->admin);

    Volt::test('admin-manage-users-page')
        ->assertSee('Name') // Table header
        ->call('nextPage') // This should not throw an error
        ->assertHasNoErrors();
});

test('user can sort by name', function () {
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Bob']);

    $this->actingAs($this->admin);

    // Default sort is by 'name' asc, so first click toggles to desc
    Volt::test('admin-manage-users-page')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc')
        ->call('sort', 'name')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'desc') // First click toggles to desc
        ->call('sort', 'name')
        ->assertSet('sortDirection', 'asc') // Second click toggles back to asc
        ->assertHasNoErrors();
});

test('user can edit another user when authorized', function () {
    $user = User::factory()->create(['name' => 'Original Name']);
    $role = Role::factory()->create();

    $this->actingAs($this->admin);

    Volt::test('admin-manage-users-page')
        ->call('openEditModal', $user->id)
        ->assertSet('editUserId', $user->id)
        ->assertSet('editUserData.name', 'Original Name')
        ->set('editUserData.name', 'Updated Name')
        ->set('editUserRoles', [$role->id])
        ->call('saveUser')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Updated Name');
});
