<?php

declare(strict_types=1);

use App\Actions\SyncDiscordRoles;
use App\Enums\MembershipLevel;
use App\Models\DiscordAccount;
use App\Models\User;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;

uses()->group('discord', 'actions');

beforeEach(function () {
    app()->instance(
        \App\Services\DiscordApiService::class,
        new FakeDiscordApiService
    );
});

it('adds the correct membership role', function () {
    config(['lighthouse.discord.roles.traveler' => '111']);
    config(['lighthouse.discord.roles.verified' => '999']);

    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordRoles::run($user);

    $addCalls = collect($fakeApi->calls)->where('method', 'addRole');
    $addedRoles = $addCalls->pluck('role_id')->toArray();

    expect($addedRoles)->toContain('111')
        ->and($addedRoles)->toContain('999');
});

it('skips sync when user has no discord accounts', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordRoles::run($user);

    expect($fakeApi->calls)->toBeEmpty();
});

it('skips brigged accounts', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    DiscordAccount::factory()->brigged()->create(['user_id' => $user->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    SyncDiscordRoles::run($user);

    expect($fakeApi->calls)->toBeEmpty();
});

it('records activity when syncing', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    SyncDiscordRoles::run($user);

    expect(\App\Models\ActivityLog::where('subject_id', $user->id)
        ->where('action', 'discord_roles_synced')->exists())->toBeTrue();
});
