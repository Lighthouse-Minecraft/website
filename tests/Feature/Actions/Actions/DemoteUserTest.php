<?php

declare(strict_types=1);

use App\Actions\DemoteUser;
use App\Enums\MembershipLevel;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('actions');

it('demotes a user one level down', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    DemoteUser::run($user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);
});

it('does not demote below the minimum level', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    DemoteUser::run($user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Drifter);
});

it('does not demote stowaway below default minimum level', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    DemoteUser::run($user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Drifter);
});

it('respects a custom minimum level', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    DemoteUser::run($user, MembershipLevel::Traveler);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
});

it('records activity when demoting', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    DemoteUser::run($user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'user_demoted',
    ]);
});

it('does not record activity when at minimum level', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    DemoteUser::run($user);

    $this->assertDatabaseMissing('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'user_demoted',
    ]);
});
