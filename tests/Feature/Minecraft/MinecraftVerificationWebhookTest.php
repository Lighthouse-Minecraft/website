<?php

declare(strict_types=1);

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;

beforeEach(function () {
    config(['services.minecraft.verification_token' => 'test-server-token']);
    $this->user = User::factory()->create();
});

test('completes verification with valid token', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'username' => 'TestPlayer',
        'uuid' => null,
        'account_type' => MinecraftAccountType::Java,
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Minecraft account verified successfully!',
        ]);

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => 'java',
    ]);

    $this->assertDatabaseHas('minecraft_verifications', [
        'id' => $verification->id,
        'status' => 'completed',
    ]);
});

test('rejects invalid server token', function () {
    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'wrong-token',
        'code' => 'ABC123',
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'message' => 'Invalid server token.',
        ]);
});

test('validates required fields', function () {
    $response = $this->postJson('/api/minecraft/verify', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['server_token', 'code', 'username', 'uuid']);
});

test('rejects non-existent verification code', function () {
    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'NONEXIST',
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Verification code not found or already used.',
        ]);
});

test('rejects expired verification code', function () {
    MinecraftVerification::factory()->for($this->user)->expired()->create([
        'code' => 'EXPIRED',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'EXPIRED',
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertStatus(410)
        ->assertJson([
            'success' => false,
            'message' => 'Verification code has expired.',
        ]);
});

test('rejects already completed verification', function () {
    MinecraftVerification::factory()->for($this->user)->completed()->create([
        'code' => 'COMPLETED',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'COMPLETED',
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Verification code not found or already used.',
        ]);
});

test('rejects duplicate uuid', function () {
    $existingUser = User::factory()->create();
    MinecraftAccount::factory()->for($existingUser)->create([
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'success' => false,
            'message' => 'This Minecraft account is already linked to a website account.',
        ]);
});

test('accepts uuid with or without dashes', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'username' => 'TestPlayer',
        'uuid' => '069a79f444e94726a5befca90e38aaf5', // No dashes
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('minecraft_accounts', [
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5', // Stored with dashes
    ]);
});

test('rate limits requests', function () {
    for ($i = 0; $i < 31; $i++) {
        $response = $this->postJson('/api/minecraft/verify', [
            'server_token' => 'test-server-token',
            'code' => 'TEST',
            'username' => 'Test',
            'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        ]);
    }

    expect($response->status())->toBe(429);
});

test('case insensitive code matching', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'abc123', // lowercase
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful();
});

test('records activity log on successful verification', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
    ]);

    $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'user_id' => $this->user->id,
        'action' => 'minecraft_account_linked',
    ]);
});
