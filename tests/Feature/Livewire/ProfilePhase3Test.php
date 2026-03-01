<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\ParentChildLink;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal', 'profile');

it('shows age badge for staff viewing profile', function () {
    $child = User::factory()->minor()->create([
        'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
    ]);

    $admin = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
    ]);
    actingAs($admin);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertSee('Age 10');
});

it('hides age badge for non-staff', function () {
    $child = User::factory()->minor()->create([
        'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
    ]);

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertDontSee('Age 10');
});

it('shows red badge for under-13', function () {
    $child = User::factory()->minor()->create([
        'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
    ]);

    $admin = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
    ]);
    actingAs($admin);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertSee('Age 10');
});

it('shows blue badge for 13-16', function () {
    $teen = User::factory()->minor()->create([
        'date_of_birth' => now()->subYears(14)->format('Y-m-d'),
    ]);

    $admin = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
    ]);
    actingAs($admin);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $teen])
        ->assertSee('Age 14');
});

it('shows gray badge for adult', function () {
    $adult = User::factory()->adult()->create([
        'date_of_birth' => now()->subYears(25)->format('Y-m-d'),
    ]);

    $admin = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
    ]);
    actingAs($admin);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $adult])
        ->assertSee('Age 25');
});

it('shows parent portal link for officers on parent profile', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $officer = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
    ]);
    actingAs($officer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $parent])
        ->assertSee('View Parent Portal');
});

it('hides parent portal link for non-officers', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $crewMember = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::CrewMember,
    ]);
    actingAs($crewMember);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $parent])
        ->assertDontSee('View Parent Portal');
});

it('shows family cards on same row for user with family', function () {
    $parent = User::factory()->adult()->create(['name' => 'Row Parent']);
    $child = User::factory()->minor()->create(['name' => 'Row Child']);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertSee('Family')
        ->assertSee('Row Parent');
});
