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

it('shows Parent Portal link in Family card for user with User - Manager role viewing parent profile', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $staff = User::factory()
        ->withRole('User - Manager')
        ->create([
            'membership_level' => MembershipLevel::Citizen,
        ]);
    actingAs($staff);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $parent])
        ->assertSee('Family')
        ->assertSee('View Parent Portal');
});

it('hides Parent Portal link from non-staff', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    actingAs($viewer);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $parent])
        ->assertSee('Family')
        ->assertDontSee('View Parent Portal');
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
