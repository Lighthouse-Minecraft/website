<?php

declare(strict_types=1);

use App\Models\MinecraftAccount;
use App\Models\MinecraftCommandLog;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Artisan;

test('cleanup command removes expired verifications', function () {
    $expiredVerification = MinecraftVerification::factory()->expired()->create([
        'status' => 'pending',
        'minecraft_username' => 'ExpiredPlayer',
    ]);

    $activeVerification = MinecraftVerification::factory()->pending()->create([
        'expires_at' => now()->addMinutes(15),
    ]);

    Artisan::call('minecraft:cleanup-expired');

    expect($expiredVerification->fresh()->status)->toBe('expired')
        ->and($activeVerification->fresh()->status)->toBe('pending');
});

test('cleanup command sends whitelist remove command via rcon', function () {
    $user = User::factory()->create();
    MinecraftVerification::factory()->for($user)->expired()->create([
        'status' => 'pending',
        'minecraft_username' => 'ExpiredPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);
    MinecraftAccount::factory()->for($user)->verifying()->create([
        'username' => 'ExpiredPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'command_id' => 'ExpiredPlayer',
    ]);

    $rconMock = $this->mock(MinecraftRconService::class);
    $rconMock->shouldReceive('executeCommand')
        ->once()
        ->with('whitelist remove ExpiredPlayer', 'whitelist', 'ExpiredPlayer', \Mockery::any(), \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Removed']);
    $rconMock->shouldReceive('executeCommand')
        ->once()
        ->with('kick "ExpiredPlayer" Your verification has expired. Please re-verify to rejoin.', 'kick', 'ExpiredPlayer', \Mockery::any(), \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Kicked']);

    Artisan::call('minecraft:cleanup-expired');

    $this->assertDatabaseMissing('minecraft_accounts', ['username' => 'ExpiredPlayer']);
});

test('cleanup command runs successfully', function () {
    MinecraftVerification::factory()->count(3)->expired()->pending()->create();

    $exitCode = Artisan::call('minecraft:cleanup-expired');

    expect($exitCode)->toBe(0);
});

test('rcon service logs commands', function () {
    $user = User::factory()->create();
    $service = app(MinecraftRconService::class);

    $result = $service->executeCommand(
        'whitelist add TestPlayer',
        'whitelist_add',
        'TestPlayer',
        $user,
        ['test' => 'data']
    );

    $this->assertDatabaseHas('minecraft_command_logs', [
        'user_id' => $user->id,
        'command' => 'whitelist add TestPlayer',
        'command_type' => 'whitelist_add',
        'target' => 'TestPlayer',
    ]);
});

test('rcon service records execution time', function () {
    $user = User::factory()->create();
    $service = app(MinecraftRconService::class);

    $service->executeCommand('list', 'server_list', null, $user);

    $log = MinecraftCommandLog::latest()->first();
    expect($log->execution_time_ms)->toBeGreaterThan(0);
});

test('rcon service handles connection failure', function () {
    config(['services.minecraft.rcon_host' => 'invalid-host']);

    $user = User::factory()->create();
    $service = app(MinecraftRconService::class);

    $result = $service->executeCommand('list', 'test', null, $user);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not->toBeEmpty();

    $this->assertDatabaseHas('minecraft_command_logs', [
        'status' => 'failed',
    ]);
});
