<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('policies', 'applicant-review', 'roles');

// == review-staff-applications gate == //

it('grants review-staff-applications to Applicant Review - All', function () {
    $user = User::factory()->withRole('Applicant Review - All')->create();

    expect($user->can('review-staff-applications'))->toBeTrue();
});

it('grants review-staff-applications list to Applicant Review - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Applicant Review - Department')
        ->create();

    expect($user->can('review-staff-applications'))->toBeTrue();
});

it('grants review-staff-applications for same-department application', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Applicant Review - Department')
        ->create();

    $position = StaffPosition::factory()->create(['department' => StaffDepartment::Command]);
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeTrue();
});

it('denies review-staff-applications for different-department application', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Applicant Review - Department')
        ->create();

    $position = StaffPosition::factory()->create(['department' => StaffDepartment::Chaplain]);
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeFalse();
});

it('grants review-staff-applications for any department to Applicant Review - All', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Applicant Review - All')
        ->create();

    $position = StaffPosition::factory()->create(['department' => StaffDepartment::Chaplain]);
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeTrue();
});

it('denies review-staff-applications without applicant review role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Staff Access')
        ->create();

    expect($user->can('review-staff-applications'))->toBeFalse();
});

it('grants review-staff-applications to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('review-staff-applications'))->toBeTrue();
});

// == StaffApplicationPolicy: viewAny == //

it('grants staff application viewAny to Applicant Review - All', function () {
    $user = User::factory()->withRole('Applicant Review - All')->create();

    expect($user->can('viewAny', StaffApplication::class))->toBeTrue();
});

it('grants staff application viewAny to Applicant Review - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Applicant Review - Department')
        ->create();

    expect($user->can('viewAny', StaffApplication::class))->toBeTrue();
});

it('denies staff application viewAny without applicant review role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    expect($user->can('viewAny', StaffApplication::class))->toBeFalse();
});
