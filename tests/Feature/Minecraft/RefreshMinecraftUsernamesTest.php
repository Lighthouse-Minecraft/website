<?php

declare(strict_types=1);

use App\Models\MinecraftAccount;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

test('refreshes usernames in staggered batches', function () {
    // Create 60 accounts (should process 2 per day for 30-day cycle)
    MinecraftAccount::factory()->count(60)->java()->create([
        'last_username_check_at' => now()->subDays(31),
    ]);

    Http::fake([
        'sessionserver.mojang.com/*' => Http::response([
            'name' => 'UpdatedName',
        ]),
    ]);

    Artisan::call('minecraft:refresh-usernames');

    // Should only update 2 accounts (60/30 = 2)
    expect(MinecraftAccount::whereDate('last_username_check_at', today())->count())->toBe(2);
});

test('prioritizes accounts from active users', function () {
    $activeUser = User::factory()->create(['last_login_at' => now()]);
    $inactiveUser = User::factory()->create(['last_login_at' => now()->subYear()]);

    $activeAccount = MinecraftAccount::factory()->for($activeUser)->java()->create([
        'username' => 'ActivePlayer',
        'last_username_check_at' => now()->subDays(31),
    ]);

    $inactiveAccount = MinecraftAccount::factory()->for($inactiveUser)->java()->create([
        'username' => 'InactivePlayer',
        'last_username_check_at' => now()->subDays(32),
    ]);

    Http::fake([
        'sessionserver.mojang.com/*' => Http::response(['name' => 'UpdatedName']),
    ]);

    Artisan::call('minecraft:refresh-usernames');

    // Active user's account should be refreshed first
    expect($activeAccount->fresh()->last_username_check_at->isToday())->toBeTrue()
        ->and($inactiveAccount->fresh()->last_username_check_at->isToday())->toBeFalse();
});

test('updates java username from mojang api', function () {
    $account = MinecraftAccount::factory()->java()->create([
        'username' => 'OldName',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'last_username_check_at' => now()->subDays(31),
    ]);

    Http::fake([
        'sessionserver.mojang.com/session/minecraft/profile/*' => Http::response([
            'name' => 'NewName',
        ]),
    ]);

    Artisan::call('minecraft:refresh-usernames');

    expect($account->fresh()->username)->toBe('NewName')
        ->and($account->fresh()->last_username_check_at)->not->toBeNull();
});

test('updates bedrock username from mcprofile api', function () {
    $account = MinecraftAccount::factory()->bedrock()->create([
        'username' => '.OldBedrockName',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'last_username_check_at' => now()->subDays(31),
    ]);

    Http::fake([
        'mcprofile.io/api/bedrock/uuid/*' => Http::response([
            'username' => '.NewBedrockName',
        ]),
    ]);

    Artisan::call('minecraft:refresh-usernames');

    expect($account->fresh()->username)->toBe('.NewBedrockName');
});

test('handles api failures gracefully', function () {
    $account = MinecraftAccount::factory()->java()->create([
        'username' => 'TestPlayer',
        'last_username_check_at' => now()->subDays(31),
    ]);

    Http::fake([
        'sessionserver.mojang.com/*' => Http::response(null, 500),
    ]);

    Artisan::call('minecraft:refresh-usernames');

    // Should still update last_username_check_at to avoid retrying immediately
    expect($account->fresh()->last_username_check_at->isToday())->toBeTrue()
        ->and($account->fresh()->username)->toBe('TestPlayer');
});

test('skips recently checked accounts', function () {
    $account = MinecraftAccount::factory()->create([
        'last_username_check_at' => now()->subDays(5), // Recently checked
    ]);

    Artisan::call('minecraft:refresh-usernames');

    // Should not update
    expect($account->fresh()->last_username_check_at->isToday())->toBeFalse();
});

test('command runs successfully', function () {
    MinecraftAccount::factory()->count(5)->create([
        'last_username_check_at' => now()->subDays(31),
    ]);

    Http::fake(['*' => Http::response(['name' => 'Test'])]);

    $exitCode = Artisan::call('minecraft:refresh-usernames');

    expect($exitCode)->toBe(0);
});
