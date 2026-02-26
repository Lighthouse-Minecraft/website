<?php

declare(strict_types=1);

use App\Actions\SyncDiscordStaff;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\ActivityLog;
use App\Models\DiscordAccount;
use App\Models\User;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;

uses()->group('discord', 'actions');

beforeEach(function () {
    app()->instance(DiscordApiService::class, new FakeDiscordApiService);
});

it('syncs staff department and rank roles', function () {
    config(['lighthouse.discord.roles.staff_engineer' => '501']);
    config(['lighthouse.discord.roles.rank_crew_member' => '601']);

    $user = User::factory()->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)->create();
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordStaff::run($user, $user->staff_department);

    $syncCalls = collect($fakeApi->calls)->where('method', 'syncManagedRoles');
    expect($syncCalls)->toHaveCount(1);

    $desiredRoles = $syncCalls->first()['desired_role_ids'];
    expect($desiredRoles)->toContain('501')
        ->and($desiredRoles)->toContain('601');
});

it('removes all staff roles when department is null', function () {
    config(['lighthouse.discord.roles.staff_command' => '500']);

    $user = User::factory()->create(['staff_department' => null, 'staff_rank' => StaffRank::None]);
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordStaff::run($user, null);

    $syncCalls = collect($fakeApi->calls)->where('method', 'syncManagedRoles');
    expect($syncCalls)->toHaveCount(1);

    $desiredRoles = $syncCalls->first()['desired_role_ids'];
    expect($desiredRoles)->toBeEmpty();
});

it('skips sync when user has no discord accounts', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordStaff::run($user, $user->staff_department);

    expect($fakeApi->calls)->toBeEmpty();
});

it('skips brigged accounts', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)->create();
    DiscordAccount::factory()->brigged()->create(['user_id' => $user->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordStaff::run($user, $user->staff_department);

    expect($fakeApi->calls)->toBeEmpty();
});

it('records activity when syncing staff roles', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember)->create();
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    SyncDiscordStaff::run($user, $user->staff_department);

    expect(ActivityLog::where('subject_id', $user->id)
        ->where('subject_type', User::class)
        ->where('action', 'discord_staff_synced')->exists())->toBeTrue();
});

it('records removal activity when department is null', function () {
    $user = User::factory()->create();
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    SyncDiscordStaff::run($user, null);

    expect(ActivityLog::where('subject_id', $user->id)
        ->where('subject_type', User::class)
        ->where('action', 'discord_staff_removed')->exists())->toBeTrue();
});

it('includes all staff role ids in managed set', function () {
    config(['lighthouse.discord.roles.staff_command' => '500']);
    config(['lighthouse.discord.roles.staff_chaplain' => '501']);
    config(['lighthouse.discord.roles.staff_engineer' => '502']);
    config(['lighthouse.discord.roles.staff_quartermaster' => '503']);
    config(['lighthouse.discord.roles.staff_steward' => '504']);
    config(['lighthouse.discord.roles.rank_jr_crew' => '600']);
    config(['lighthouse.discord.roles.rank_crew_member' => '601']);
    config(['lighthouse.discord.roles.rank_officer' => '602']);

    $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordStaff::run($user, $user->staff_department);

    $syncCalls = collect($fakeApi->calls)->where('method', 'syncManagedRoles');
    $managedRoles = $syncCalls->first()['managed_role_ids'];

    expect($managedRoles)->toContain('500', '501', '502', '503', '504', '600', '601', '602');
});
