<?php

declare(strict_types=1);

use App\Actions\CompleteVerification;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Notifications\MinecraftCommandNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = new CompleteVerification;

    // Mock RCON service for all tests
    $this->mock(\App\Services\MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => 'OK', 'error' => null]);
    });
});

test('completes verification and creates account', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'account_type' => 'java',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    // Create the verifying account (as GenerateVerificationCode would)
    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => 'java',
    ]);

    $result = $this->action->handle(
        'ABC123',
        'TestPlayer',
        '069a79f4-44e9-4726-a5be-fca90e38aaf5'
    );

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('minecraft_verifications', [
        'id' => $verification->id,
        'status' => 'completed',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);
});

test('fails if verification not found', function () {
    $result = $this->action->handle(
        'NONEXISTENT',
        'TestPlayer',
        '069a79f4-44e9-4726-a5be-fca90e38aaf5'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Invalid or expired verification code.');
});

test('fails if verification expired', function () {
    MinecraftVerification::factory()->for($this->user)->expired()->create([
        'code' => 'EXPIRED',
        'status' => 'pending',
    ]);

    $result = $this->action->handle(
        'EXPIRED',
        'TestPlayer',
        '069a79f4-44e9-4726-a5be-fca90e38aaf5'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('expired');
});

test('fails if uuid already linked', function () {
    $existingUser = User::factory()->create();
    MinecraftAccount::factory()->for($existingUser)->create([
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $result = $this->action->handle(
        'ABC123',
        'TestPlayer',
        '069a79f4-44e9-4726-a5be-fca90e38aaf5'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('This Minecraft account is already linked to another user.');
});

test('uses database transaction', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    // Force an error after creating account but before updating verification
    MinecraftAccount::creating(function () {
        throw new \Exception('Test rollback');
    });

    try {
        $this->action->handle('ABC123', 'TestPlayer', '069a79f4-44e9-4726-a5be-fca90e38aaf5');
    } catch (\Exception $e) {
        // Expected
    } finally {
        MinecraftAccount::flushEventListeners();
    }

    // Verification should not be marked complete due to rollback
    expect($verification->fresh()->status)->toBe('pending');
    $this->assertDatabaseMissing('minecraft_accounts', [
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);
});

test('records activity log', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'account_type' => 'java',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => 'java',
    ]);

    $this->action->handle('ABC123', 'TestPlayer', '069a79f4-44e9-4726-a5be-fca90e38aaf5');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => \App\Models\User::class,
        'subject_id' => $this->user->id,
        'action' => 'minecraft_account_linked',
    ]);
});

test('normalizes uuid with dashes', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    // Create the verifying account (as GenerateVerificationCode would)
    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => 'java',
    ]);

    $result = $this->action->handle('ABC123', 'TestPlayer', '069a79f444e94726a5befca90e38aaf5');

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'status' => 'active',
    ]);
});

test('completes verification for bedrock account with dot-prefix username', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'BED123',
        'account_type' => 'bedrock',
        'minecraft_username' => '.BedrockPlayer',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => '.BedrockPlayer',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
    ]);

    $result = $this->action->handle(
        'BED123',
        '.BedrockPlayer',
        '00000000-0000-0000-0009-01234567890a'
    );

    expect($result['success'])->toBeTrue();
});

test('completes verification for linked bedrock account using bedrock fallback', function () {
    // Website stored the Floodgate identity
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'LNK123',
        'account_type' => 'bedrock',
        'minecraft_username' => '.Ghostridr6007',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => '.Ghostridr6007',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
    ]);

    // Plugin sends the linked Java identity as minecraft_username/uuid,
    // plus the bedrock_username for fallback matching
    $result = $this->action->handle(
        'LNK123',
        'Ghostridr',                              // linked Java username (won't match stored)
        'a008f810-1af7-48fa-8a3d-cbc07e29c811',   // linked Java UUID (won't match stored)
        bedrockUsername: 'Ghostridr6007',
        bedrockXuid: '2535406112136054',
    );

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => '.Ghostridr6007',
        'status' => 'active',
        'bedrock_xuid' => '2535406112136054',
    ]);
});

test('stores bedrock xuid on verification', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'BDX123',
        'account_type' => 'bedrock',
        'minecraft_username' => '.BedrockPlayer',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => '.BedrockPlayer',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
    ]);

    $result = $this->action->handle(
        'BDX123',
        '.BedrockPlayer',
        '00000000-0000-0000-0009-01234567890a',
        bedrockXuid: '2535406112136054',
    );

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'bedrock_xuid' => '2535406112136054',
    ]);
});

test('does not overwrite existing bedrock xuid', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'BDX456',
        'account_type' => 'bedrock',
        'minecraft_username' => '.BedrockPlayer',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => '.BedrockPlayer',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
        'bedrock_xuid' => 'existing-xuid-value',
    ]);

    $result = $this->action->handle(
        'BDX456',
        '.BedrockPlayer',
        '00000000-0000-0000-0009-01234567890a',
        bedrockXuid: 'new-xuid-value',
    );

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'bedrock_xuid' => 'existing-xuid-value',
    ]);
});

test('bedrock fallback does not match when bedrock_username is not provided', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'NOB123',
        'account_type' => 'bedrock',
        'minecraft_username' => '.BedrockPlayer',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => '.BedrockPlayer',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
    ]);

    // Send mismatched Java identity without bedrock fallback fields
    $result = $this->action->handle(
        'NOB123',
        'SomeJavaPlayer',
        'a008f810-1af7-48fa-8a3d-cbc07e29c811',
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Username or UUID mismatch.');
});

test('completes verification for unlinked bedrock with clean gamertag stored and dot-prefixed incoming', function () {
    // New flow: website stores clean gamertag (no dot)
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'CLN123',
        'account_type' => 'bedrock',
        'minecraft_username' => 'BedrockPlayer',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'BedrockPlayer',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
    ]);

    // Plugin sends dot-prefixed name (Floodgate in-game name), same UUID
    $result = $this->action->handle(
        'CLN123',
        '.BedrockPlayer',
        '00000000-0000-0000-0009-01234567890a',
    );

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => 'BedrockPlayer',
        'status' => 'active',
    ]);
});

test('completes verification for linked bedrock with clean gamertag via bedrock_username', function () {
    // New flow: website stores clean gamertag (no dot)
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'CLN456',
        'account_type' => 'bedrock',
        'minecraft_username' => 'Ghostridr6007',
        'minecraft_uuid' => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username' => 'Ghostridr6007',
        'uuid' => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
    ]);

    // Plugin sends linked Java identity, plus bedrock_username for fallback
    $result = $this->action->handle(
        'CLN456',
        'Ghostridr',
        'a008f810-1af7-48fa-8a3d-cbc07e29c811',
        bedrockUsername: 'Ghostridr6007',
        bedrockXuid: '2535406112136054',
    );

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => 'Ghostridr6007',
        'status' => 'active',
        'bedrock_xuid' => '2535406112136054',
    ]);
});

test('syncs staff position when staff member verifies account', function () {
    Notification::fake();

    // Create staff user with server access
    $staffUser = User::factory()->create([
        'membership_level' => \App\Enums\MembershipLevel::Traveler,
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
        'staff_title' => 'Test Officer',
    ]);

    $verification = MinecraftVerification::factory()->for($staffUser)->pending()->create([
        'code' => 'ABC123',
        'account_type' => 'java',
        'minecraft_username' => 'StaffPlayer',
        'minecraft_uuid' => '169a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    // Create the verifying account
    MinecraftAccount::factory()->for($staffUser)->verifying()->create([
        'username' => 'StaffPlayer',
        'uuid' => '169a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => 'java',
    ]);

    $action = new CompleteVerification;
    $result = $action->handle(
        'ABC123',
        'StaffPlayer',
        '169a79f4-44e9-4726-a5be-fca90e38aaf5'
    );

    expect($result['success'])->toBeTrue();

    // Verify account is active
    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $staffUser->id,
        'username' => 'StaffPlayer',
        'status' => 'active',
    ]);

    // setmember command dispatched for Traveler rank assignment
    Notification::assertSentOnDemand(
        MinecraftCommandNotification::class,
        fn ($n) => str_contains($n->command, 'lh setmember')
    );

    // setstaff command dispatched for staff position sync
    Notification::assertSentOnDemand(
        MinecraftCommandNotification::class,
        fn ($n) => str_contains($n->command, 'lh setstaff')
    );
});
