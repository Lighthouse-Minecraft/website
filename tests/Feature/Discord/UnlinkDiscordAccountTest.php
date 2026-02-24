<?php

declare(strict_types=1);

use App\Actions\UnlinkDiscordAccount;
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
    $account = DiscordAccount::factory()->create(['user_id' => $user->id]);

    UnlinkDiscordAccount::run($account, $user);

    expect(DiscordAccount::find($account->id))->toBeNull();
});

it('calls removeAllManagedRoles on discord api', function () {
    $user = User::factory()->create();
    $account = DiscordAccount::factory()->create(['user_id' => $user->id]);

    // Bind as singleton so we can track calls
    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    UnlinkDiscordAccount::run($account, $user);

    $removeAllCalls = collect($fakeApi->calls)->where('method', 'removeAllManagedRoles');
    expect($removeAllCalls)->toHaveCount(1);
});

it('records activity when unlinking', function () {
    $user = User::factory()->create();
    $account = DiscordAccount::factory()->create(['user_id' => $user->id]);

    UnlinkDiscordAccount::run($account, $user);

    expect(\App\Models\ActivityLog::where('subject_id', $user->id)
        ->where('action', 'discord_account_unlinked')->exists())->toBeTrue();
});
