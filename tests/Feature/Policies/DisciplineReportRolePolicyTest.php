<?php

declare(strict_types=1);

use App\Models\DisciplineReport;
use App\Models\User;

uses()->group('policies', 'discipline-reports', 'roles');

// == viewAny == //

it('grants discipline report viewAny to Discipline Report - Manager', function () {
    $user = User::factory()->withRole('Discipline Report - Manager')->create();

    expect($user->can('viewAny', DisciplineReport::class))->toBeTrue();
});

it('grants discipline report viewAny to Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('viewAny', DisciplineReport::class))->toBeTrue();
});

it('denies discipline report viewAny without appropriate role', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', DisciplineReport::class))->toBeFalse();
});

it('grants discipline report viewAny to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('viewAny', DisciplineReport::class))->toBeTrue();
});

// == create == //

it('grants discipline report create to Discipline Report - Manager', function () {
    $user = User::factory()->withRole('Discipline Report - Manager')->create();

    expect($user->can('create', DisciplineReport::class))->toBeTrue();
});

it('denies discipline report create without Discipline Report - Manager role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('create', DisciplineReport::class))->toBeFalse();
});

// == update == //

it('grants discipline report update to Discipline Report - Manager for draft', function () {
    $user = User::factory()->withRole('Discipline Report - Manager')->create();
    $report = DisciplineReport::factory()->create();

    expect($user->can('update', $report))->toBeTrue();
});

it('grants discipline report update to reporter for own draft', function () {
    $reporter = User::factory()->withRole('Staff Access')->create();
    $report = DisciplineReport::factory()->create(['reporter_user_id' => $reporter->id]);

    expect($reporter->can('update', $report))->toBeTrue();
});

it('denies discipline report update to non-reporter without Manager role', function () {
    $user = User::factory()->withRole('Staff Access')->create();
    $report = DisciplineReport::factory()->create();

    expect($user->can('update', $report))->toBeFalse();
});

// == publish == //

it('grants discipline report publish to Discipline Report - Publisher for draft', function () {
    $user = User::factory()->withRole('Discipline Report - Publisher')->create();
    $report = DisciplineReport::factory()->create();

    expect($user->can('publish', $report))->toBeTrue();
});

it('denies discipline report publish without Publisher role', function () {
    $user = User::factory()->withRole('Discipline Report - Manager')->create();
    $report = DisciplineReport::factory()->create();

    expect($user->can('publish', $report))->toBeFalse();
});
