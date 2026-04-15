<?php

declare(strict_types=1);

use App\Actions\PutUserInBrig;
use App\Models\User;
use App\Notifications\BrigTimerExpiredNotification;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

uses()->group('brig', 'permanent');

// ─── canAppeal() ─────────────────────────────────────────────────────────────

it('canAppeal returns false when permanent_brig_at is set', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Duplicate account',
        'permanent_brig_at' => now(),
        'next_appeal_available_at' => null,
    ]);

    expect($user->canAppeal())->toBeFalse();
});

it('canAppeal returns false for permanent user even when next_appeal_available_at is in the past', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'permanent_brig_at' => now()->subDay(),
        'next_appeal_available_at' => now()->subHour(),
    ]);

    expect($user->canAppeal())->toBeFalse();
});

// ─── PutUserInBrig appeal timer logic ────────────────────────────────────────

it('PutUserInBrig sets next_appeal_available_at to brig_expires_at when expires_at is provided', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();
    $expiresAt = now()->addDays(30);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    PutUserInBrig::run($target, $admin, 'Test reason', $expiresAt);

    $fresh = $target->fresh();
    expect($fresh->next_appeal_available_at)->not->toBeNull()
        ->and($fresh->next_appeal_available_at->timestamp)->toBe($expiresAt->timestamp);
});

it('PutUserInBrig defaults next_appeal_available_at to 24h when no expires_at is provided', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    PutUserInBrig::run($target, $admin, 'Test reason');

    $fresh = $target->fresh();
    expect($fresh->next_appeal_available_at)->not->toBeNull()
        ->and($fresh->next_appeal_available_at->diffInHours(now(), true))->toBeLessThanOrEqual(24);
});

it('PutUserInBrig sets permanent_brig_at and clears next_appeal_available_at when permanent is true', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    PutUserInBrig::run($target, $admin, 'Duplicate account ban', permanent: true);

    $fresh = $target->fresh();
    expect($fresh->in_brig)->toBeTrue()
        ->and($fresh->permanent_brig_at)->not->toBeNull()
        ->and($fresh->next_appeal_available_at)->toBeNull()
        ->and($fresh->brig_expires_at)->toBeNull();
});

// ─── CheckBrigTimers ─────────────────────────────────────────────────────────

it('CheckBrigTimers skips users with permanent_brig_at set', function () {
    Notification::fake();

    User::factory()->create([
        'in_brig' => true,
        'permanent_brig_at' => now()->subDay(),
        'brig_expires_at' => now()->subHour(),
        'brig_timer_notified' => false,
    ]);

    $this->artisan('brig:check-timers');

    Notification::assertNothingSent();
});

it('CheckBrigTimers processes non-permanent users with expired timers', function () {
    Notification::fake();

    $user = User::factory()->create([
        'in_brig' => true,
        'permanent_brig_at' => null,
        'brig_expires_at' => now()->subHour(),
        'brig_timer_notified' => false,
        'notification_preferences' => ['account' => ['email' => true, 'pushover' => false]],
    ]);

    $this->artisan('brig:check-timers');

    Notification::assertSentTo($user, BrigTimerExpiredNotification::class);
});

// ─── in-brig-card Permanently Confined message ───────────────────────────────

it('in-brig-card shows Permanently Confined for users with permanent_brig_at', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Duplicate account',
        'permanent_brig_at' => now(),
    ]);

    $this->actingAs($user);

    Volt::test('dashboard.in-brig-card')
        ->assertSee('Permanently Confined')
        ->assertDontSee('Submit Appeal')
        ->assertDontSee('Appeal Not Yet Available');
});
