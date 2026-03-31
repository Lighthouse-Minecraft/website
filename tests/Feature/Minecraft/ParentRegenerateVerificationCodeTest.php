<?php

declare(strict_types=1);

use App\Actions\ParentRegenerateVerificationCode;
use App\Enums\MinecraftAccountStatus;
use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\ParentChildLink;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('parent-portal', 'minecraft');

beforeEach(function () {
    $this->parent = User::factory()->adult()->create();
    $this->child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $this->parent->id, 'child_user_id' => $this->child->id]);

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => true, 'response' => 'OK']);
    });
});

it('parent can restart verification for a cancelled child account', function () {
    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $this->child->id,
        'username' => 'ChildPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $result = ParentRegenerateVerificationCode::run($account, $this->parent);

    expect($result['success'])->toBeTrue()
        ->and($result['code'])->toHaveLength(6)
        ->and($result['expires_at'])->not->toBeNull()
        ->and($result['error'])->toBeNull();

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Verifying);

    $this->assertDatabaseHas('minecraft_verifications', [
        'user_id' => $this->child->id,
        'minecraft_username' => 'ChildPlayer',
        'status' => 'pending',
    ]);
});

it('parent can restart verification for a cancelling child account', function () {
    $account = MinecraftAccount::factory()->cancelling()->create([
        'user_id' => $this->child->id,
    ]);

    $result = ParentRegenerateVerificationCode::run($account, $this->parent);

    expect($result['success'])->toBeTrue();
    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Verifying);
});

it('records activity on child account after restart', function () {
    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $this->child->id,
    ]);

    ParentRegenerateVerificationCode::run($account, $this->parent);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $this->child->id,
        'action' => 'minecraft_verification_regenerated',
    ]);
});

it('fails when parent-child relationship does not exist', function () {
    $otherChild = User::factory()->minor()->create();
    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $otherChild->id,
    ]);

    $result = ParentRegenerateVerificationCode::run($account, $this->parent);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('permission');
    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Cancelled);
});

it('fails when child is in brig', function () {
    $this->child->update(['in_brig' => true]);
    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $this->child->id,
    ]);

    $result = ParentRegenerateVerificationCode::run($account, $this->parent);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('brig');
});

it('fails when minecraft access is parent-disabled', function () {
    $this->child->update(['parent_allows_minecraft' => false]);
    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $this->child->id,
    ]);

    $result = ParentRegenerateVerificationCode::run($account, $this->parent);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('disabled');
});

it('applies rate limiting against child user id not parent', function () {
    $rateLimit = config('lighthouse.minecraft_verification_rate_limit_per_hour');

    // Create verifications scoped to the child (not the parent)
    MinecraftVerification::factory()->count($rateLimit)->create([
        'user_id' => $this->child->id,
        'status' => 'pending',
        'expires_at' => now()->addHour(),
        'created_at' => now()->subMinutes(30),
    ]);

    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $this->child->id,
    ]);

    $result = ParentRegenerateVerificationCode::run($account, $this->parent);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('rate limit');
});

it('does not change account state when server is offline', function () {
    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => false, 'response' => null, 'error' => 'Connection refused']);
    });

    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $this->child->id,
    ]);

    $result = ParentRegenerateVerificationCode::run($account, $this->parent);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('offline');
    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Cancelled);
    $this->assertDatabaseMissing('minecraft_verifications', ['user_id' => $this->child->id]);
});
