<?php

declare(strict_types=1);

use App\Actions\CheckDocumentVisibility;
use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;

uses()->group('docs', 'actions');

it('allows anyone to view public pages', function () {
    $result = CheckDocumentVisibility::run('public');

    expect($result)->toBeTrue();
});

it('allows logged-in users to view users pages', function () {
    $user = User::factory()->create();
    loginAs($user);

    $result = CheckDocumentVisibility::run('users');

    expect($result)->toBeTrue();
});

it('blocks guests from users pages', function () {
    CheckDocumentVisibility::run('users');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('allows residents to view resident pages', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
    loginAs($user);

    $result = CheckDocumentVisibility::run('resident');

    expect($result)->toBeTrue();
});

it('blocks travelers from resident pages', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    loginAs($user);

    CheckDocumentVisibility::run('resident');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('allows citizens to view citizen pages', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();
    loginAs($user);

    $result = CheckDocumentVisibility::run('citizen');

    expect($result)->toBeTrue();
});

it('blocks residents from citizen pages', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
    loginAs($user);

    CheckDocumentVisibility::run('citizen');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('allows staff to view staff pages', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew)->withRole('Staff Access')->create();
    loginAs($user);

    $result = CheckDocumentVisibility::run('staff');

    expect($result)->toBeTrue();
});

it('blocks non-staff from staff pages', function () {
    $user = User::factory()->create();
    loginAs($user);

    CheckDocumentVisibility::run('staff');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('allows officers to view officer pages', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->withRole('Officer Docs - Viewer')->create();
    loginAs($user);

    $result = CheckDocumentVisibility::run('officer');

    expect($result)->toBeTrue();
});

it('blocks jr crew from officer pages', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew)->withRole('Staff Access')->create();
    loginAs($user);

    CheckDocumentVisibility::run('officer');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('allows admins to view all visibility levels', function () {
    $admin = loginAsAdmin();

    expect(CheckDocumentVisibility::run('public'))->toBeTrue();
    expect(CheckDocumentVisibility::run('users'))->toBeTrue();
    expect(CheckDocumentVisibility::run('staff'))->toBeTrue();
    expect(CheckDocumentVisibility::run('officer'))->toBeTrue();
});

it('blocks brigged users from users pages', function () {
    $user = User::factory()->create(['in_brig' => true]);
    loginAs($user);

    CheckDocumentVisibility::run('users');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
