<?php

declare(strict_types=1);

use App\Actions\LinkDiscordAccount;
use App\Enums\MembershipLevel;
use App\Models\DiscordAccount;
use App\Models\User;

uses()->group('discord', 'actions');

beforeEach(function () {
    app()->instance(
        \App\Services\DiscordApiService::class,
        new \App\Services\FakeDiscordApiService
    );
});

it('links a discord account to a user', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    $result = LinkDiscordAccount::run($user, [
        'id' => '123456789',
        'username' => 'testuser',
        'global_name' => 'Test User',
        'avatar' => 'abc123',
        'access_token' => 'token123',
        'refresh_token' => 'refresh123',
        'expires_in' => 604800,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['account'])->toBeInstanceOf(DiscordAccount::class)
        ->and($user->discordAccounts()->count())->toBe(1);
});

it('prevents linking when account limit is reached', function () {
    config(['lighthouse.max_discord_accounts' => 1]);
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    DiscordAccount::factory()->create(['user_id' => $user->id]);

    $result = LinkDiscordAccount::run($user, [
        'id' => '987654321',
        'username' => 'anotheruser',
        'access_token' => 'token',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Maximum');
});

it('prevents linking a discord id already linked to another user', function () {
    $user1 = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $user2 = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    DiscordAccount::factory()->create([
        'user_id' => $user1->id,
        'discord_user_id' => '123456789',
    ]);

    $result = LinkDiscordAccount::run($user2, [
        'id' => '123456789',
        'username' => 'sameuser',
        'access_token' => 'token',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('already linked');
});

it('records activity when linking', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    LinkDiscordAccount::run($user, [
        'id' => '123456789',
        'username' => 'testuser',
        'access_token' => 'token',
    ]);

    expect(\App\Models\ActivityLog::where('subject_id', $user->id)
        ->where('subject_type', User::class)
        ->where('action', 'discord_account_linked')->exists())->toBeTrue();
});
