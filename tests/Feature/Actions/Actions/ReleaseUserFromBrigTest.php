<?php

declare(strict_types=1);

use App\Actions\ReleaseUserFromBrig;
use App\Enums\EmailDigestFrequency;
use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Notifications\UserReleasedFromBrigNotification;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('brig', 'actions');

it('marks the target user as no longer in brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Old reason',
        'brig_expires_at' => now()->addDays(10),
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($target->fresh()->in_brig)->toBeFalse()
        ->and($target->fresh()->brig_reason)->toBeNull()
        ->and($target->fresh()->brig_expires_at)->toBeNull();
});

it('restores banned minecraft accounts to active', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);
    $account = MinecraftAccount::factory()->create([
        'user_id' => $target->id,
        'status' => MinecraftAccountStatus::Banned,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Pardoned');

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

it('does not affect non-banned minecraft accounts', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);
    $activeAccount = MinecraftAccount::factory()->active()->create(['user_id' => $target->id]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($activeAccount->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

it('records activity for user released from brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Good behavior');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'user_released_from_brig',
    ]);
});

it('sends notification to the released user', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['tickets' => ['email' => true, 'pushover' => false]],
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    Notification::assertSentTo($target, UserReleasedFromBrigNotification::class);
});

it('clears next_appeal_available_at on release', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'next_appeal_available_at' => now()->addDays(5),
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($target->fresh()->next_appeal_available_at)->toBeNull();
});

it('sets brig_timer_notified to false on release', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_timer_notified' => true,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($target->fresh()->brig_timer_notified)->toBeFalse();
});
