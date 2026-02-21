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
        'command_id' => 'TestPlayer',
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
        'command_id' => 'TestPlayer',
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
        'command_id' => 'TestPlayer',
    ]);

    $result = $this->action->handle('ABC123', 'TestPlayer', '069a79f444e94726a5befca90e38aaf5');

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_accounts', [
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'status' => 'active',
    ]);
});
test('syncs staff position when staff member verifies account', function () {
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
        'command_id' => 'StaffPlayer',
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
