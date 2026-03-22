<?php

declare(strict_types=1);

use App\Models\DisciplineReport;
use App\Models\User;

uses()->group('discipline-reports', 'policies');

it('allows admin to perform any action', function () {
    $admin = loginAsAdmin();
    $report = DisciplineReport::factory()->create();

    expect($admin->can('viewAny', DisciplineReport::class))->toBeTrue()
        ->and($admin->can('view', $report))->toBeTrue()
        ->and($admin->can('create', DisciplineReport::class))->toBeTrue()
        ->and($admin->can('update', $report))->toBeTrue()
        ->and($admin->can('publish', $report))->toBeTrue();
});

it('staff with Staff Access can view but not create or publish without specific roles', function () {
    $officer = officerCommand();
    loginAs($officer);
    $report = DisciplineReport::factory()->create();

    expect($officer->can('viewAny', DisciplineReport::class))->toBeTrue()
        ->and($officer->can('view', $report))->toBeTrue()
        ->and($officer->can('create', DisciplineReport::class))->toBeFalse()
        ->and($officer->can('update', $report))->toBeFalse()
        ->and($officer->can('publish', $report))->toBeFalse();
});

it('allows staff with Staff Access to view any reports', function () {
    $staff = jrCrewQuartermaster();
    loginAs($staff);

    expect($staff->can('viewAny', DisciplineReport::class))->toBeTrue();
});

it('allows user with Discipline Report - Manager to create reports', function () {
    $user = User::factory()->withRole('Discipline Report - Manager')->create();
    loginAs($user);

    expect($user->can('create', DisciplineReport::class))->toBeTrue();
});

it('allows report creator to update their draft report', function () {
    $creator = jrCrewQuartermaster();
    loginAs($creator);
    $report = DisciplineReport::factory()->byReporter($creator)->create();

    expect($creator->can('update', $report))->toBeTrue();
});

it('allows user with Discipline Report - Manager to update any draft report', function () {
    $manager = User::factory()->withRole('Discipline Report - Manager')->create();
    loginAs($manager);
    $report = DisciplineReport::factory()->create();

    expect($manager->can('update', $report))->toBeTrue();
});

it('prevents updating a published report', function () {
    $creator = jrCrewQuartermaster();
    loginAs($creator);
    $report = DisciplineReport::factory()->byReporter($creator)->published()->create();

    expect($creator->can('update', $report))->toBeFalse();
});

it('allows user with Discipline Report - Publisher role to publish a draft report', function () {
    $user = User::factory()->withRole('Discipline Report - Publisher')->create();
    loginAs($user);
    $report = DisciplineReport::factory()->create();

    expect($user->can('publish', $report))->toBeTrue();
});

it('prevents non-officer from publishing', function () {
    $crew = crewQuartermaster();
    loginAs($crew);
    $report = DisciplineReport::factory()->create();

    expect($crew->can('publish', $report))->toBeFalse();
});

it('allows subject user to view their published report', function () {
    $subject = User::factory()->create();
    loginAs($subject);
    $report = DisciplineReport::factory()->forSubject($subject)->published()->create();

    expect($subject->can('view', $report))->toBeTrue();
});

it('prevents subject user from viewing draft reports', function () {
    $subject = User::factory()->create();
    loginAs($subject);
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    expect($subject->can('view', $report))->toBeFalse();
});

it('allows parent to view published reports about their child', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create();
    $parent->children()->attach($child);
    loginAs($parent);

    $report = DisciplineReport::factory()->forSubject($child)->published()->create();

    expect($parent->can('view', $report))->toBeTrue();
});

it('prevents parent from viewing draft reports about their child', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create();
    $parent->children()->attach($child);
    loginAs($parent);

    $report = DisciplineReport::factory()->forSubject($child)->create();

    expect($parent->can('view', $report))->toBeFalse();
});

it('prevents non-staff non-subject from viewing reports', function () {
    $randomUser = User::factory()->create();
    loginAs($randomUser);
    $report = DisciplineReport::factory()->published()->create();

    expect($randomUser->can('view', $report))->toBeFalse();
});

it('prevents deletion of reports', function () {
    $admin = loginAsAdmin();
    $report = DisciplineReport::factory()->create();

    expect($admin->can('delete', $report))->toBeFalse();
});
