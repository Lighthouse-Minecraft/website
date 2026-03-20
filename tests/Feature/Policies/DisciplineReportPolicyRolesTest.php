<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\User;
use App\Policies\DisciplineReportPolicy;

uses()->group('discipline-reports', 'policies', 'roles');

// === before() hook ===

it('admin bypasses discipline report policy via before hook except delete and publish', function () {
    $admin = User::factory()->admin()->create();
    $policy = new DisciplineReportPolicy;

    expect($policy->before($admin, 'viewAny'))->toBeTrue()
        ->and($policy->before($admin, 'delete'))->toBeNull()
        ->and($policy->before($admin, 'publish'))->toBeNull();
});

it('non-admin returns null from discipline report policy before hook', function () {
    $user = User::factory()->create();
    $policy = new DisciplineReportPolicy;

    expect($policy->before($user, 'viewAny'))->toBeNull();
});

it('command officer returns null from discipline report policy before hook', function () {
    $officer = officerCommand();
    $policy = new DisciplineReportPolicy;

    expect($policy->before($officer, 'viewAny'))->toBeNull();
});

// === viewAny with role ===

it('user with Manage Discipline Reports role can view any reports', function () {
    $manager = User::factory()->withRole('Manage Discipline Reports')->create();

    expect($manager->can('viewAny', DisciplineReport::class))->toBeTrue();
});

// === view with role ===

it('user with Manage Discipline Reports role can view any report', function () {
    $manager = User::factory()->withRole('Manage Discipline Reports')->create();
    $report = DisciplineReport::factory()->create();

    expect($manager->can('view', $report))->toBeTrue();
});

// === create with role ===

it('user with Manage Discipline Reports role can create reports', function () {
    $manager = User::factory()->withRole('Manage Discipline Reports')->create();

    expect($manager->can('create', DisciplineReport::class))->toBeTrue();
});

// === update with role ===

it('user with Manage Discipline Reports role can update draft reports', function () {
    $manager = User::factory()->withRole('Manage Discipline Reports')->create();
    $report = DisciplineReport::factory()->create();

    expect($manager->can('update', $report))->toBeTrue();
});

it('user with Manage Discipline Reports role cannot update published reports', function () {
    $manager = User::factory()->withRole('Manage Discipline Reports')->create();
    $report = DisciplineReport::factory()->published()->create();

    expect($manager->can('update', $report))->toBeFalse();
});

// === publish with role ===

it('user with Publish Discipline Reports role can publish draft reports', function () {
    $publisher = User::factory()->withRole('Publish Discipline Reports')->create();
    $report = DisciplineReport::factory()->create();

    expect($publisher->can('publish', $report))->toBeTrue();
});

it('user without Publish Discipline Reports role cannot publish', function () {
    $manager = User::factory()->withRole('Manage Discipline Reports')->create();
    $report = DisciplineReport::factory()->create();

    expect($manager->can('publish', $report))->toBeFalse();
});

it('user with Publish Discipline Reports role cannot publish already published report', function () {
    $publisher = User::factory()->withRole('Publish Discipline Reports')->create();
    $report = DisciplineReport::factory()->published()->create();

    expect($publisher->can('publish', $report))->toBeFalse();
});

it('reporter with Publish role cannot publish their own report about a staff member', function () {
    $reporter = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
        ->withRole('Publish Discipline Reports')
        ->create();
    $subject = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)
        ->create();
    $report = DisciplineReport::factory()
        ->byReporter($reporter)
        ->forSubject($subject)
        ->create();

    expect($reporter->can('publish', $report))->toBeFalse();
});

it('admin can publish any draft report via before hook bypass on publish', function () {
    $admin = User::factory()->admin()->create();
    $report = DisciplineReport::factory()->create();

    // Admin has Publish Discipline Reports role via hasRole admin override
    expect($admin->can('publish', $report))->toBeTrue();
});

// === regular user cannot do anything ===

it('regular user without roles cannot manage discipline reports', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', DisciplineReport::class))->toBeFalse()
        ->and($user->can('create', DisciplineReport::class))->toBeFalse();
});
