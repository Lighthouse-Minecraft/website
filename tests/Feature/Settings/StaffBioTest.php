<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;

uses()->group('staff', 'settings');

it('allows crew members to access the staff bio page', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)->create();

    $this->actingAs($user)
        ->get(route('settings.staff-bio'))
        ->assertOk();
});

it('allows officers to access the staff bio page', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();

    $this->actingAs($user)
        ->get(route('settings.staff-bio'))
        ->assertOk();
});

it('denies jr crew from accessing the staff bio page', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Steward, StaffRank::JrCrew)->create();

    $this->actingAs($user)
        ->get(route('settings.staff-bio'))
        ->assertForbidden();
});

it('denies regular users from accessing the staff bio page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.staff-bio'))
        ->assertForbidden();
});

it('denies unauthenticated users from accessing the staff bio page', function () {
    $this->get(route('settings.staff-bio'))
        ->assertRedirect(route('login'));
});
