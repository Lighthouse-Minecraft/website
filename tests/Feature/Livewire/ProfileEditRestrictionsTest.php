<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('profile', 'livewire');

it('regular user editing own profile can only update username', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com',
        'date_of_birth' => '1990-01-01',
        'parent_email' => 'parent@example.com',
    ]);
    loginAs($user);

    livewire('users.display-basic-details', ['user' => $user])
        ->set('editUserData.name', 'NewUsername')
        ->set('editUserData.email', 'hacker@example.com')
        ->set('editUserData.date_of_birth', '2000-12-31')
        ->set('editUserData.parent_email', 'evil@example.com')
        ->call('saveEditUser');

    expect($user->fresh()->name)->toBe('NewUsername')
        ->and($user->fresh()->email)->toBe('original@example.com')
        ->and($user->fresh()->date_of_birth->format('Y-m-d'))->toBe('1990-01-01')
        ->and($user->fresh()->parent_email)->toBe('parent@example.com');
});

it('user-manager can edit all fields', function () {
    $manager = User::factory()->withRole('User - Manager')->create();
    $target = User::factory()->create([
        'email' => 'original@example.com',
        'date_of_birth' => '1990-01-01',
        'parent_email' => null,
    ]);
    loginAs($manager);

    livewire('users.display-basic-details', ['user' => $target])
        ->set('editUserData.name', 'UpdatedName')
        ->set('editUserData.email', 'updated@example.com')
        ->set('editUserData.date_of_birth', '1995-06-15')
        ->call('saveEditUser');

    expect($target->fresh()->name)->toBe('UpdatedName')
        ->and($target->fresh()->email)->toBe('updated@example.com')
        ->and($target->fresh()->date_of_birth->format('Y-m-d'))->toBe('1995-06-15');
});

it('admin can edit all fields on any profile', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create([
        'email' => 'original@example.com',
        'date_of_birth' => '1990-01-01',
    ]);

    livewire('users.display-basic-details', ['user' => $target])
        ->set('editUserData.name', 'AdminEdited')
        ->set('editUserData.email', 'admin-updated@example.com')
        ->call('saveEditUser');

    expect($target->fresh()->name)->toBe('AdminEdited')
        ->and($target->fresh()->email)->toBe('admin-updated@example.com');
});

it('regular user edit modal shows protected fields as read-only text', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'date_of_birth' => '1990-01-01',
        'parent_email' => 'parent@example.com',
    ]);
    loginAs($user);

    livewire('users.display-basic-details', ['user' => $user])
        ->assertSee('Contact staff to update your email address.');
});

it('user-manager edit modal shows protected fields as inputs', function () {
    $manager = User::factory()->withRole('User - Manager')->create();
    $target = User::factory()->create();
    loginAs($manager);

    livewire('users.display-basic-details', ['user' => $target])
        ->assertDontSee('Contact staff to update your email address.');
});
