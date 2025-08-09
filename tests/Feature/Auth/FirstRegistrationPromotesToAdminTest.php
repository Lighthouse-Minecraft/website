<?php

// use App\Livewire\Auth\Register;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure an Admin role exists for the app/tests
    Role::query()->firstOrCreate(['name' => 'Admin']);
});

it('promotes the first registered user to Admin (testing env)', function () {
    Livewire::test('auth.register')
        ->set('name', 'First User')
        ->set('email', 'first@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::whereEmail('first@example.com')->firstOrFail();
    $roleId = Role::where('name', 'Admin')->value('id');

    $this->assertDatabaseHas('role_user', [
        'user_id' => $user->id,
        'role_id' => $roleId,
    ]);
});

it('does not promote a second registered user to Admin', function () {
    // First user (becomes admin)
    Livewire::test('auth.register')
        ->set('name', 'First User')
        ->set('email', 'first@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register');

    // Second user (should NOT be admin)
    Livewire::test('auth.register')
        ->set('name', 'Second User')
        ->set('email', 'second@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasNoErrors();

    $second = User::whereEmail('second@example.com')->firstOrFail();
    $roleId = Role::where('name', 'Admin')->value('id');

    $this->assertDatabaseMissing('role_user', [
        'user_id' => $second->id,
        'role_id' => $roleId,
    ]);
});
