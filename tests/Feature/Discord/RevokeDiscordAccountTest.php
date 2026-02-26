<?php

declare(strict_types=1);

use App\Actions\RevokeDiscordAccount;
use App\Models\ActivityLog;
use App\Models\DiscordAccount;
use App\Models\User;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;

uses()->group('discord', 'actions');

beforeEach(function () {
    app()->instance(DiscordApiService::class, new FakeDiscordApiService);
});

it('deletes the discord account', function () {
    $user = User::factory()->create();
    $account = DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    RevokeDiscordAccount::run($account, $user);

    expect(DiscordAccount::find($account->id))->toBeNull();
});

it('calls removeAllManagedRoles on discord api', function () {
    $user = User::factory()->create();
    $account = DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    RevokeDiscordAccount::run($account, $user);

    $removeCalls = collect($fakeApi->calls)->where('method', 'removeAllManagedRoles');
    expect($removeCalls)->toHaveCount(1)
        ->and($removeCalls->first()['discord_user_id'])->toBe($account->discord_user_id);
});

it('records activity when revoking', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $account = DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    RevokeDiscordAccount::run($account, $admin);

    expect(ActivityLog::where('subject_id', $user->id)
        ->where('subject_type', User::class)
        ->where('action', 'discord_account_revoked')->exists())->toBeTrue();
});
