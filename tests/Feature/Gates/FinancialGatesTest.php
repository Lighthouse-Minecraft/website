<?php

declare(strict_types=1);

use App\Models\User;

uses()->group('gates', 'finance');

// == financials-view == //

it('grants financials-view to user with Financials - View role', function () {
    $user = User::factory()->withRole('Financials - View')->create();

    expect($user->can('financials-view'))->toBeTrue();
});

it('grants financials-view to user with Financials - Treasurer role', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();

    expect($user->can('financials-view'))->toBeTrue();
});

it('grants financials-view to user with Financials - Manage role', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();

    expect($user->can('financials-view'))->toBeTrue();
});

it('denies financials-view to user with no financial role', function () {
    $user = User::factory()->create();

    expect($user->can('financials-view'))->toBeFalse();
});

it('grants financials-view to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('financials-view'))->toBeTrue();
});

// == financials-treasurer == //

it('grants financials-treasurer to user with Financials - Treasurer role', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();

    expect($user->can('financials-treasurer'))->toBeTrue();
});

it('grants financials-treasurer to user with Financials - Manage role', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();

    expect($user->can('financials-treasurer'))->toBeTrue();
});

it('denies financials-treasurer to user with only Financials - View role', function () {
    $user = User::factory()->withRole('Financials - View')->create();

    expect($user->can('financials-treasurer'))->toBeFalse();
});

it('denies financials-treasurer to user with no financial role', function () {
    $user = User::factory()->create();

    expect($user->can('financials-treasurer'))->toBeFalse();
});

it('grants financials-treasurer to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('financials-treasurer'))->toBeTrue();
});

// == financials-manage == //

it('grants financials-manage to user with Financials - Manage role', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();

    expect($user->can('financials-manage'))->toBeTrue();
});

it('denies financials-manage to user with only Financials - Treasurer role', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();

    expect($user->can('financials-manage'))->toBeFalse();
});

it('denies financials-manage to user with only Financials - View role', function () {
    $user = User::factory()->withRole('Financials - View')->create();

    expect($user->can('financials-manage'))->toBeFalse();
});

it('denies financials-manage to user with no financial role', function () {
    $user = User::factory()->create();

    expect($user->can('financials-manage'))->toBeFalse();
});

it('grants financials-manage to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('financials-manage'))->toBeTrue();
});

// == Gate hierarchy: manage implies treasurer implies view == //

it('verifies manage implies treasurer and view', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();

    expect($user->can('financials-manage'))->toBeTrue()
        ->and($user->can('financials-treasurer'))->toBeTrue()
        ->and($user->can('financials-view'))->toBeTrue();
});

it('verifies treasurer implies view but not manage', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();

    expect($user->can('financials-treasurer'))->toBeTrue()
        ->and($user->can('financials-view'))->toBeTrue()
        ->and($user->can('financials-manage'))->toBeFalse();
});

it('verifies view-only does not imply treasurer or manage', function () {
    $user = User::factory()->withRole('Financials - View')->create();

    expect($user->can('financials-view'))->toBeTrue()
        ->and($user->can('financials-treasurer'))->toBeFalse()
        ->and($user->can('financials-manage'))->toBeFalse();
});
