<?php

declare(strict_types=1);

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

test('cleanup command sends async whitelist remove', function () {
    MinecraftVerification::factory()->expired()->create([
        'status' => 'pending',
        'minecraft_username' => 'ExpiredPlayer',
    ]);

    Artisan::call('minecraft:cleanup-expired');

    // Should queue notification
    $this->assertDatabaseHas('jobs', [
        'queue' => 'default',
    ]);
})->skip('Queue/notification testing requires additional infrastructure setup');

test('cleanup command runs successfully', function () {
    MinecraftVerification::factory()->count(3)->expired()->create();

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
