<?php

declare(strict_types=1);

use App\Actions\RegenerateVerificationCode;
use App\Enums\MinecraftAccountStatus;
use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => true, 'response' => 'OK']);
    });
});

test('regenerates verification code for a cancelled account', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create([
        'status' => MinecraftAccountStatus::Cancelled,
        'username' => 'TestPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $result = RegenerateVerificationCode::run($account, $this->user);

    expect($result['success'])->toBeTrue();
    expect($result['code'])->toHaveLength(6);
    expect($result['expires_at'])->not->toBeNull();

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Verifying);

    $this->assertDatabaseHas('minecraft_verifications', [
        'user_id' => $this->user->id,
        'minecraft_username' => 'TestPlayer',
        'status' => 'pending',
    ]);
});

test('rejects regeneration for non-cancelled account', function () {
    $account = MinecraftAccount::factory()->for($this->user)->active()->create();

    $result = RegenerateVerificationCode::run($account, $this->user);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Only cancelled accounts can be retried.');
});

test('rejects regeneration for another users account', function () {
    $otherUser = User::factory()->create();
    $account = MinecraftAccount::factory()->for($otherUser)->create([
        'status' => MinecraftAccountStatus::Cancelled,
    ]);

    $result = RegenerateVerificationCode::run($account, $this->user);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Account does not belong to you.');
});

test('rejects regeneration when user is in brig', function () {
    $this->user->update(['in_brig' => true]);

    $account = MinecraftAccount::factory()->for($this->user)->create([
        'status' => MinecraftAccountStatus::Cancelled,
    ]);

    $result = RegenerateVerificationCode::run($account, $this->user);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('brig');
});

test('retry verification button works from settings page', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create([
        'status' => MinecraftAccountStatus::Cancelled,
        'username' => 'RetryPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    Volt::test('settings.minecraft-accounts')
        ->call('retryVerification', $account->id)
        ->assertHasNoErrors();

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Verifying);

    $this->assertDatabaseHas('minecraft_verifications', [
        'user_id' => $this->user->id,
        'minecraft_username' => 'RetryPlayer',
        'status' => 'pending',
    ]);
});

test('remove cancelled account deletes the account', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create([
        'status' => MinecraftAccountStatus::Cancelled,
    ]);

    Volt::test('settings.minecraft-accounts')
        ->call('removeCancelledAccount', $account->id);

    $this->assertDatabaseMissing('minecraft_accounts', ['id' => $account->id]);
});

test('cannot remove another users cancelled account', function () {
    $otherUser = User::factory()->create();
    $account = MinecraftAccount::factory()->for($otherUser)->create([
        'status' => MinecraftAccountStatus::Cancelled,
    ]);

    Volt::test('settings.minecraft-accounts')
        ->call('removeCancelledAccount', $account->id);

    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});
