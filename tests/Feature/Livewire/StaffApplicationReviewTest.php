<?php

declare(strict_types=1);

use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses()->group('applications', 'livewire');

it('admin can see review list', function () {
    $admin = loginAsAdmin();

    Livewire::test('staff-applications.review-list')
        ->assertOk();
});

it('user with Applicant Review - All role can see review list', function () {
    $reviewer = \App\Models\User::factory()
        ->withRole('Applicant Review - All')
        ->create();
    actingAs($reviewer);

    Livewire::test('staff-applications.review-list')
        ->assertOk();
});

it('user with Applicant Review - Department role can access review page', function () {
    $reviewer = \App\Models\User::factory()
        ->withStaffPosition(\App\Enums\StaffDepartment::Engineer, \App\Enums\StaffRank::CrewMember)
        ->withRole('Applicant Review - Department')
        ->create();
    actingAs($reviewer);

    Livewire::test('staff-applications.review-list')
        ->assertOk();
});

it('staff without applicant review role cannot access review page', function () {
    $staff = crewEngineer();
    actingAs($staff);

    Livewire::test('staff-applications.review-list')
        ->assertForbidden();
});
