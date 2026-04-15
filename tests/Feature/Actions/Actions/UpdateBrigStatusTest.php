<?php

declare(strict_types=1);

use App\Actions\UpdateBrigStatus;
use App\Models\User;
use App\Notifications\BrigStatusUpdatedNotification;
use App\Notifications\UserReleasedFromBrigNotification;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('brig', 'actions');

beforeEach(function () {
    app()->instance(DiscordApiService::class, new FakeDiscordApiService);
});

// ─── Reason change ────────────────────────────────────────────────────────────

it('updates brig reason and logs activity', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Old reason',
    ]);

    UpdateBrigStatus::run($target, $admin, newReason: 'New reason');

    expect($target->fresh()->brig_reason)->toBe('New reason');
    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'brig_status_updated',
    ]);
});

// ─── Timer adjustment ─────────────────────────────────────────────────────────

it('updates brig expiry and logs activity', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_expires_at' => now()->addDays(7),
    ]);
    $newExpiry = now()->addDays(30);

    UpdateBrigStatus::run($target, $admin, newExpiresAt: $newExpiry);

    expect($target->fresh()->brig_expires_at->timestamp)->toBe($newExpiry->timestamp);
    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'brig_status_updated',
    ]);
});

it('clears brig expiry when newExpiresAt is explicitly null', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_expires_at' => now()->addDays(7),
    ]);

    UpdateBrigStatus::run($target, $admin, newExpiresAt: null);

    expect($target->fresh()->brig_expires_at)->toBeNull();
});

// ─── Permanent set ────────────────────────────────────────────────────────────

it('sets permanent_brig_at and clears expiry and appeal timer', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_expires_at' => now()->addDays(30),
        'next_appeal_available_at' => now()->addDays(30),
    ]);

    UpdateBrigStatus::run($target, $admin, permanent: true);

    $fresh = $target->fresh();
    expect($fresh->permanent_brig_at)->not->toBeNull()
        ->and($fresh->brig_expires_at)->toBeNull()
        ->and($fresh->next_appeal_available_at)->toBeNull();
});

it('logs permanent_brig_set when setting permanent', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);

    UpdateBrigStatus::run($target, $admin, permanent: true);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'permanent_brig_set',
    ]);
});

// ─── Permanent remove ─────────────────────────────────────────────────────────

it('clears permanent_brig_at and recalculates appeal timer from brig_expires_at', function () {
    $admin = User::factory()->create();
    $expiresAt = now()->addDays(14);
    $target = User::factory()->create([
        'in_brig' => true,
        'permanent_brig_at' => now(),
        'brig_expires_at' => $expiresAt,
        'next_appeal_available_at' => null,
    ]);

    UpdateBrigStatus::run($target, $admin, permanent: false);

    $fresh = $target->fresh();
    expect($fresh->permanent_brig_at)->toBeNull()
        ->and($fresh->next_appeal_available_at->timestamp)->toBe($expiresAt->timestamp);
});

it('clears permanent_brig_at and sets appeal timer to 24h when no brig_expires_at', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'permanent_brig_at' => now(),
        'brig_expires_at' => null,
        'next_appeal_available_at' => null,
    ]);

    UpdateBrigStatus::run($target, $admin, permanent: false);

    $fresh = $target->fresh();
    expect($fresh->permanent_brig_at)->toBeNull()
        ->and($fresh->next_appeal_available_at)->not->toBeNull()
        ->and($fresh->next_appeal_available_at->diffInHours(now(), true))->toBeLessThanOrEqual(24);
});

it('logs permanent_brig_removed when removing permanent', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'permanent_brig_at' => now(),
    ]);

    UpdateBrigStatus::run($target, $admin, permanent: false);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'permanent_brig_removed',
    ]);
});

// ─── Notifications ────────────────────────────────────────────────────────────

it('sends BrigStatusUpdatedNotification when notify is true', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Old']);

    UpdateBrigStatus::run($target, $admin, newReason: 'New', notify: true);

    Notification::assertSentTo($target, BrigStatusUpdatedNotification::class);
});

it('does not send notification when notify is false', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Old']);

    UpdateBrigStatus::run($target, $admin, newReason: 'New', notify: false);

    Notification::assertNotSentTo($target, BrigStatusUpdatedNotification::class);
});

it('always sends notification when removing permanent, even with notify false', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'permanent_brig_at' => now(),
    ]);

    UpdateBrigStatus::run($target, $admin, permanent: false, notify: false);

    Notification::assertSentTo($target, BrigStatusUpdatedNotification::class);
});

// ─── Quick release ────────────────────────────────────────────────────────────

it('quick release delegates to ReleaseUserFromBrig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    UpdateBrigStatus::run($target, $admin, releaseReason: 'Staff decision to release');

    expect($target->fresh()->in_brig)->toBeFalse();
});

it('quick release logs brig_status_updated activity', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    UpdateBrigStatus::run($target, $admin, releaseReason: 'Good behavior');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'brig_status_updated',
    ]);
});

it('quick release sends UserReleasedFromBrigNotification', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    UpdateBrigStatus::run($target, $admin, releaseReason: 'Released');

    Notification::assertSentTo($target, UserReleasedFromBrigNotification::class);
});
