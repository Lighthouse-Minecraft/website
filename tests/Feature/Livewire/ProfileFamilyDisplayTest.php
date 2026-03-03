<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\ParentChildLink;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal', 'profile');

it('shows Child Account badge for user with parents', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertSee('Child Account');
});

it('does not show Child Account badge for user without parents', function () {
    $user = User::factory()->adult()->create();

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $user])
        ->assertDontSee('Child Account');
});

it('shows Family card for user with parents', function () {
    $parent = User::factory()->adult()->create(['name' => 'Parent McParent']);
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertSee('Family')
        ->assertSee('Parents')
        ->assertSee('Parent McParent');
});

it('shows Family card for user with children', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create(['name' => 'Child McChild']);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $parent])
        ->assertSee('Family')
        ->assertSee('Children')
        ->assertSee('Child McChild');
});

it('does not show Family card for user with no family links', function () {
    $user = User::factory()->adult()->create();

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $user])
        ->assertDontSee('Family');
});

it('shows admin Parental Controls card for staff', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create(['parent_allows_minecraft' => false]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $admin = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
    ]);
    actingAs($admin);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertSee('Parental Controls (Staff)')
        ->assertSee('Permission States')
        ->assertSee('MC: Denied');
});

it('hides admin Parental Controls card for non-staff', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertDontSee('Parental Controls (Staff)');
});

it('shows action dropdown for staff on profile', function () {
    $target = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $admin = User::factory()->create([
        'membership_level' => MembershipLevel::Citizen,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
    ]);
    actingAs($admin);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $target])
        ->assertSee('Actions');
});
