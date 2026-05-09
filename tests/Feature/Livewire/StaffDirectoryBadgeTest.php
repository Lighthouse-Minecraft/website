<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Models\BackgroundCheck;
use App\Models\SiteConfig;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('background-checks', 'livewire', 'staff');

it('shows green badge with passed tooltip on officer card when user has a passed background check', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create(['title' => 'Commander']);
    BackgroundCheck::factory()->passed()->create([
        'user_id' => $user->id,
        'completed_date' => '2024-06-15',
    ]);

    $this->get(route('staff.index'))
        ->assertSee('Background check passed on Jun 15, 2024');
});

it('shows zinc badge with waived tooltip on officer card when user has a waived background check', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();
    BackgroundCheck::factory()->waived()->create(['user_id' => $user->id]);

    $this->get(route('staff.index'))
        ->assertSee('A background check is not required for this position');
});

it('shows amber badge with no-record message when user has no terminal background check', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    $this->get(route('staff.index'))
        ->assertSee('Waiting for more donations to come in before we can do more background checks');
});

it('uses SiteConfig value for no-record message', function () {
    SiteConfig::setValue('bg_check_no_record_message', 'Custom no-record message for test');

    $user = User::factory()->create();
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    $this->get(route('staff.index'))
        ->assertSee('Custom no-record message for test');
});

it('uses most recent terminal check, not a pending or deliberating one', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    BackgroundCheck::factory()->passed()->create([
        'user_id' => $user->id,
        'completed_date' => '2023-01-01',
    ]);
    BackgroundCheck::factory()->create(['user_id' => $user->id]);

    $this->get(route('staff.index'))
        ->assertSee('Background check passed on Jan 1, 2023');
});

it('shows no badge on unfilled position cards', function () {
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create([
        'user_id' => null,
        'title' => 'Open Officer Role',
    ]);

    $this->get(route('staff.index'))
        ->assertSee('Open Position')
        ->assertDontSee('BG Check');
});

it('shows badge on crew member cards', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->crewMember()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();
    BackgroundCheck::factory()->passed()->create([
        'user_id' => $user->id,
        'completed_date' => '2024-03-20',
    ]);

    $this->get(route('staff.index'))
        ->assertSee('Background check passed on Mar 20, 2024');
});

it('badge is visible to unauthenticated visitors', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();
    BackgroundCheck::factory()->passed()->create([
        'user_id' => $user->id,
        'completed_date' => '2024-01-01',
    ]);

    $this->get(route('staff.index'))
        ->assertSee('Background check passed on Jan 1, 2024');
});
