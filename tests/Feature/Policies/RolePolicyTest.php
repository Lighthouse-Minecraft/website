<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Policies\RolePolicy;

uses()->group('policies', 'roles');

// === before() hook ===

it('admin bypasses all role policy checks via before hook', function () {
    $admin = User::factory()->admin()->create();
    $policy = new RolePolicy;

    expect($policy->before($admin, 'viewAny'))->toBeTrue();
});

it('non-admin returns null from role policy before hook', function () {
    $user = User::factory()->create();
    $policy = new RolePolicy;

    expect($policy->before($user, 'viewAny'))->toBeNull();
});

it('command officer returns null from role policy before hook', function () {
    $officer = officerCommand();
    $policy = new RolePolicy;

    expect($policy->before($officer, 'viewAny'))->toBeNull();
});

// === viewAny ===

it('admin can view any roles', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('viewAny', Role::class))->toBeTrue();
});

it('non-admin cannot view any roles', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Role::class))->toBeFalse();
});

// === create ===

it('admin can create roles', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('create', Role::class))->toBeTrue();
});

it('non-admin cannot create roles', function () {
    $user = officerCommand();

    expect($user->can('create', Role::class))->toBeFalse();
});

// === update ===

it('admin can update roles', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::create(['name' => 'Test Role For Update']);

    expect($admin->can('update', $role))->toBeTrue();
});

it('non-admin cannot update roles', function () {
    $user = officerCommand();
    $role = Role::create(['name' => 'Test Role For Non Admin Update']);

    expect($user->can('update', $role))->toBeFalse();
});
