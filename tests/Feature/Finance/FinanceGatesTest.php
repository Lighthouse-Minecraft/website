<?php

declare(strict_types=1);

use App\Models\User;

uses()->group('finance', 'gates');

// == finance-view == //

it('grants finance-view to user with Finance - View role', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    expect($user->can('finance-view'))->toBeTrue();
});

it('grants finance-view to user with Finance - Record role', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    expect($user->can('finance-view'))->toBeTrue();
});

it('grants finance-view to user with Finance - Manage role', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    expect($user->can('finance-view'))->toBeTrue();
});

it('denies finance-view to user with no finance role', function () {
    $user = User::factory()->create();

    expect($user->can('finance-view'))->toBeFalse();
});

// == finance-record == //

it('grants finance-record to user with Finance - Record role', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    expect($user->can('finance-record'))->toBeTrue();
});

it('grants finance-record to user with Finance - Manage role', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    expect($user->can('finance-record'))->toBeTrue();
});

it('denies finance-record to user with only Finance - View role', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    expect($user->can('finance-record'))->toBeFalse();
});

it('denies finance-record to user with no finance role', function () {
    $user = User::factory()->create();

    expect($user->can('finance-record'))->toBeFalse();
});

// == finance-manage == //

it('grants finance-manage to user with Finance - Manage role', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    expect($user->can('finance-manage'))->toBeTrue();
});

it('denies finance-manage to user with only Finance - Record role', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    expect($user->can('finance-manage'))->toBeFalse();
});

it('denies finance-manage to user with only Finance - View role', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    expect($user->can('finance-manage'))->toBeFalse();
});

it('denies finance-manage to user with no finance role', function () {
    $user = User::factory()->create();

    expect($user->can('finance-manage'))->toBeFalse();
});

// == route protection == //

it('denies unauthenticated user access to finance routes', function () {
    $this->get(route('finance.accounts.index'))
        ->assertRedirect(route('login'));
});

it('denies user without finance role access to finance routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.accounts.index'))
        ->assertForbidden();
});

it('allows Finance - View user access to finance routes', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.accounts.index'))
        ->assertOk();
});
