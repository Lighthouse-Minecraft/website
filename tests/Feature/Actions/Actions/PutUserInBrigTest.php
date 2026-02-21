<?php

declare(strict_types=1);

use App\Actions\PutUserInBrig;
use App\Actions\RecordActivity;
use App\Enums\EmailDigestFrequency;
use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Notifications\UserPutInBrigNotification;
use App\Services\MinecraftRconService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses()->group('brig', 'actions');

it('marks the target user as in brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    PutUserInBrig::run($target, $admin, 'Test reason');

    expect($target->fresh()->in_brig)->toBeTrue()
        ->and($target->fresh()->brig_reason)->toBe('Test reason');
});

it('sets brig_expires_at when expiresAt is provided', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    $expiresAt = now()->addDays(7);
    PutUserInBrig::run($target, $admin, 'Test reason', $expiresAt);

    expect($target->fresh()->brig_expires_at)->not->toBeNull();
});

it('sets next_appeal_available_at when appealAvailableAt is provided', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    $appealAt = now()->addDays(3);
    PutUserInBrig::run($target, $admin, 'Test reason', null, $appealAt);

    expect($target->fresh()->next_appeal_available_at)->not->toBeNull();
});

it('sets brig_timer_notified to false', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['brig_timer_notified' => true]);

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    PutUserInBrig::run($target, $admin, 'Test reason');

    expect($target->fresh()->brig_timer_notified)->toBeFalse();
});

it('bans active minecraft accounts', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $target->id]);

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    PutUserInBrig::run($target, $admin, 'Test reason');

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Banned);
});

it('bans verifying minecraft accounts', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();
    $account = MinecraftAccount::factory()->verifying()->create(['user_id' => $target->id]);

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    PutUserInBrig::run($target, $admin, 'Test reason');

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Banned);
});

it('records activity for user put in brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    PutUserInBrig::run($target, $admin, 'Bad behavior');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'user_put_in_brig',
    ]);
});

it('sends notification to the target user', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['tickets' => ['email' => true, 'pushover' => false]],
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    PutUserInBrig::run($target, $admin, 'Test reason');

    Notification::assertSentTo($target, UserPutInBrigNotification::class);
});

it('works without expires_at or appeal_available_at', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('sendCommand')->andReturn(true);

    PutUserInBrig::run($target, $admin, 'Test reason');

    expect($target->fresh()->in_brig)->toBeTrue()
        ->and($target->fresh()->brig_expires_at)->toBeNull()
        ->and($target->fresh()->next_appeal_available_at)->toBeNull();
});
