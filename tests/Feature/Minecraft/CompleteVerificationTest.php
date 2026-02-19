<?php

declare(strict_types=1);

use App\Actions\CompleteVerification;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = new CompleteVerification;
});

test('completes verification and creates account', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'account_type' => 'java',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
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
    // TODO: Enable when activity_log table is created
    // $this->assertDatabaseHas('activity_log', [
    //     'user_id' => $this->user->id,
    //     'action' => 'minecraft_account_linked',
    // ]);
})->skip('Requires activity_log table to be created');

test('normalizes uuid with dashes', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $result = $this->action->handle('ABC123', 'TestPlayer', '069a79f444e94726a5befca90e38aaf5');

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);
});
