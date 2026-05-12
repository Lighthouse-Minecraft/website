<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\BackgroundCheck;
use App\Models\Meeting;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('background-checks', 'livewire', 'meeting');

function staffInCommand(): User
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember, 'Test Crew')
        ->create();
}

it('shows Overdue badge when staff has no Passed background check', function () {
    $actor = officerCommand();
    $member = staffInCommand();
    $meeting = Meeting::factory()->create();

    loginAs($actor);

    Volt::test('meeting.department-report-cards', ['meeting' => $meeting, 'department' => StaffDepartment::Command->value])
        ->assertSee('Overdue');
});

it('shows Overdue badge when most recent Passed check expired over 2 years ago', function () {
    $actor = officerCommand();
    $member = staffInCommand();
    $meeting = Meeting::factory()->create();
    BackgroundCheck::factory()->passed()->create([
        'user_id' => $member->id,
        'completed_date' => now()->subYears(2)->subDay()->toDateString(),
    ]);

    loginAs($actor);

    Volt::test('meeting.department-report-cards', ['meeting' => $meeting, 'department' => StaffDepartment::Command->value])
        ->assertSee('Overdue');
});

it('shows Due Soon badge when Passed check expires within 90 days', function () {
    $actor = officerCommand();
    $member = staffInCommand();
    $meeting = Meeting::factory()->create();
    BackgroundCheck::factory()->passed()->create([
        'user_id' => $member->id,
        'completed_date' => now()->subYears(2)->addDays(89)->toDateString(),
    ]);

    loginAs($actor);

    Volt::test('meeting.department-report-cards', ['meeting' => $meeting, 'department' => StaffDepartment::Command->value])
        ->assertSee('Due Soon');
});

it('shows Waived badge when most recent terminal check is Waived', function () {
    $actor = officerCommand();
    $member = staffInCommand();
    $meeting = Meeting::factory()->create();
    BackgroundCheck::factory()->waived()->create(['user_id' => $member->id]);

    loginAs($actor);

    Volt::test('meeting.department-report-cards', ['meeting' => $meeting, 'department' => StaffDepartment::Command->value])
        ->assertSee('Waived');
});

it('shows no badge when staff has a current valid Passed check', function () {
    $actor = officerEngineer();
    $member = staffInCommand();
    $meeting = Meeting::factory()->create();
    BackgroundCheck::factory()->passed()->create([
        'user_id' => $member->id,
        'completed_date' => now()->subYear()->toDateString(),
    ]);

    loginAs($actor);

    Volt::test('meeting.department-report-cards', ['meeting' => $meeting, 'department' => StaffDepartment::Command->value])
        ->assertDontSee('Overdue')
        ->assertDontSee('Due Soon')
        ->assertDontSee('Waived');
});

it('shows no badge when Pending record exists but prior Passed check is still valid', function () {
    $actor = officerEngineer();
    $member = staffInCommand();
    $meeting = Meeting::factory()->create();

    BackgroundCheck::factory()->passed()->create([
        'user_id' => $member->id,
        'completed_date' => now()->subYear()->toDateString(),
    ]);
    BackgroundCheck::factory()->create(['user_id' => $member->id]);

    loginAs($actor);

    Volt::test('meeting.department-report-cards', ['meeting' => $meeting, 'department' => StaffDepartment::Command->value])
        ->assertDontSee('Overdue')
        ->assertDontSee('Due Soon')
        ->assertDontSee('Waived');
});
