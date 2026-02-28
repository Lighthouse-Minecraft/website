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
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    // Create the verifying account (as GenerateVerificationCode would)
    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Minecraft account successfully linked!',
        ]);

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => 'java',
        'status' => 'active',
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
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'message' => 'Invalid server token.',
        ]);
});

test('validates required fields', function () {
    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'minecraft_username', 'minecraft_uuid']);
});

test('rejects non-existent verification code', function () {
    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABCDEF',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => false,
        ]);
});

test('rejects expired verification code', function () {
    MinecraftVerification::factory()->for($this->user)->expired()->create([
        'code' => 'EXPIRE',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'EXPIRE',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => false,
        ]);
});

test('rejects already completed verification', function () {
    MinecraftVerification::factory()->for($this->user)->completed()->create([
        'code' => 'COMPLT',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'COMPLT',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => false,
        ]);
});

test('rejects duplicate uuid', function () {
    $existingUser = User::factory()->create();
    MinecraftAccount::factory()->for($existingUser)->create([
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'minecraft_username' => 'TestPlayer',
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => false,
        ]);
});

test('accepts uuid with or without dashes', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    // Create the verifying account (as GenerateVerificationCode would)
    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f444e94726a5befca90e38aaf5', // No dashes
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('minecraft_accounts', [
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5', // Stored with dashes
        'status' => 'active',
    ]);
});

test('completes verification for linked bedrock account via webhook', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'R4MEZ2',
        'account_type' => MinecraftAccountType::Bedrock,
        'minecraft_username' => '.Ghostridr6007',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => '.Ghostridr6007',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => MinecraftAccountType::Bedrock,
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'R4MEZ2',
        'minecraft_username' => 'Ghostridr',
        'minecraft_uuid' => 'a008f810-1af7-48fa-8a3d-cbc07e29c811',
        'is_bedrock' => true,
        'bedrock_username' => 'Ghostridr6007',
        'bedrock_xuid' => '2535406112136054',
        'is_linked' => true,
        'linked_java_username' => 'Ghostridr',
        'linked_java_uuid' => 'a008f810-1af7-48fa-8a3d-cbc07e29c811',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Minecraft account successfully linked!',
        ]);

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => '.Ghostridr6007',
        'status' => 'active',
        'bedrock_xuid' => '2535406112136054',
    ]);
});

test('rate limits requests', function () {
    for ($i = 0; $i < 31; $i++) {
        $response = $this->postJson('/api/minecraft/verify', [
            'server_token' => 'test-server-token',
            'code' => 'TESTRT',
            'minecraft_username' => 'Test',
            'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        ]);
    }

    expect($response->status())->toBe(429);
});

test('case insensitive code matching', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $response = $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'abc123', // lowercase
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $response->assertSuccessful();
});

test('records activity log on successful verification', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $this->postJson('/api/minecraft/verify', [
        'server_token' => 'test-server-token',
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => \App\Models\User::class,
        'subject_id' => $this->user->id,
        'action' => 'minecraft_account_linked',
    ]);
});
