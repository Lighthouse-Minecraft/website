<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('profile');

it('shows Rules Agreed By Self for staff with manage-stowaway-users gate when user agreed themselves', function () {
    $staff = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Quartermaster Crew')
        ->withRole('Staff Access')
        ->withRole('Membership Level - Manager')
        ->create();
    actingAs($staff);

    $member = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'rules_accepted_at' => now(),
    ]);
    $member->update(['rules_accepted_by_user_id' => $member->id]);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $member])
        ->assertSee('Rules Agreed By')
        ->assertSee('Self');
});

it('shows parent name and email when a parent agreed on behalf of child', function () {
    $staff = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Quartermaster Crew')
        ->withRole('Staff Access')
        ->withRole('Membership Level - Manager')
        ->create();
    actingAs($staff);

    $parent = User::factory()->adult()->create(['name' => 'Parent Person', 'email' => 'parentperson@example.com']);
    $child = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'rules_accepted_at' => now(),
        'rules_accepted_by_user_id' => $parent->id,
    ]);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $child])
        ->assertSee('Rules Agreed By')
        ->assertSee('Parent Person')
        ->assertSee('parentperson@example.com')
        ->assertSee('(parent)');
});

it('shows Not yet agreed when rules_accepted_by_user_id is null', function () {
    $staff = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Quartermaster Crew')
        ->withRole('Staff Access')
        ->withRole('Membership Level - Manager')
        ->create();
    actingAs($staff);

    $member = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create([
        'rules_accepted_by_user_id' => null,
    ]);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $member])
        ->assertSee('Rules Agreed By')
        ->assertSee('Not yet agreed');
});

it('does not show Rules Agreed By to users without manage-stowaway-users gate', function () {
    $viewer = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    actingAs($viewer);

    $member = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'rules_accepted_at' => now(),
    ]);
    $member->update(['rules_accepted_by_user_id' => $member->id]);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $member])
        ->assertDontSee('Rules Agreed By');
});

it('shows Rules Agreed By for all membership levels including Drifter and Citizen', function () {
    $staff = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Quartermaster Crew')
        ->withRole('Staff Access')
        ->withRole('Membership Level - Manager')
        ->create();
    actingAs($staff);

    foreach ([MembershipLevel::Drifter, MembershipLevel::Citizen] as $level) {
        $member = User::factory()->withMembershipLevel($level)->create([
            'rules_accepted_by_user_id' => null,
        ]);

        Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $member])
            ->assertSee('Rules Agreed By');
    }
});
