<?php

declare(strict_types=1);

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->rconMock = $this->mock(MinecraftRconService::class);
});

it('removes whitelist and kicks player when verification expires', function () {
    MinecraftVerification::factory()->for($this->user)->create([
        'status' => 'pending',
        'expires_at' => now()->subMinutes(35),
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'command_id' => 'TestPlayer',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->withArgs(fn ($cmd) => str_contains($cmd, 'whitelist remove'))
        ->once()
        ->andReturn(['success' => true, 'response' => 'OK']);

    $this->rconMock->shouldReceive('executeCommand')
        ->withArgs(fn ($cmd) => str_contains($cmd, 'kick "TestPlayer"'))
        ->once()
        ->andReturn(['success' => true, 'response' => 'OK']);

    $this->artisan('minecraft:cleanup-expired')->assertSuccessful();

    $this->assertDatabaseMissing('minecraft_accounts', ['username' => 'TestPlayer']);
    $this->assertDatabaseHas('minecraft_verifications', ['status' => 'expired']);
});

it('does not crash if kick fails and still deletes account', function () {
    MinecraftVerification::factory()->for($this->user)->create([
        'status' => 'pending',
        'expires_at' => now()->subMinutes(35),
        'minecraft_username' => 'OfflinePlayer',
        'minecraft_uuid' => '169a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'OfflinePlayer',
        'uuid' => '169a79f4-44e9-4726-a5be-fca90e38aaf5',
        'command_id' => 'OfflinePlayer',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->withArgs(fn ($cmd) => str_contains($cmd, 'whitelist remove'))
        ->once()
        ->andReturn(['success' => true, 'response' => 'OK']);

    $this->rconMock->shouldReceive('executeCommand')
        ->withArgs(fn ($cmd) => str_contains($cmd, 'kick "OfflinePlayer"'))
        ->once()
        ->andReturn(['success' => false, 'response' => 'Player not online']);

    $this->artisan('minecraft:cleanup-expired')->assertSuccessful();

    $this->assertDatabaseMissing('minecraft_accounts', ['username' => 'OfflinePlayer']);
    $this->assertDatabaseHas('minecraft_verifications', ['status' => 'expired']);
});
