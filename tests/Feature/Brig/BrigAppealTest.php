<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('brig', 'appeals');

test('users can appeal when appeal timer has expired', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'brig_expires_at' => now()->addDays(30),
        'next_appeal_available_at' => now()->subHour(),
    ]);

    expect($user->canAppeal())->toBeTrue();
});

test('users cannot appeal when appeal timer has not expired', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'brig_expires_at' => now()->addDays(30),
        'next_appeal_available_at' => now()->addDays(5),
    ]);

    expect($user->canAppeal())->toBeFalse();
});

test('users can appeal immediately when no appeal timer is set', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'brig_expires_at' => now()->addDays(30),
        'next_appeal_available_at' => null,
    ]);

    expect($user->canAppeal())->toBeTrue();
});

test('users not in brig cannot appeal', function () {
    $user = User::factory()->create([
        'in_brig' => false,
        'next_appeal_available_at' => now()->subHour(),
    ]);

    expect($user->canAppeal())->toBeFalse();
});

test('submitting appeal sets next appeal timer', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'brig_expires_at' => now()->addDays(30),
        'next_appeal_available_at' => null,
    ]);

    $this->actingAs($user);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', 'I would like to appeal this decision.')
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->next_appeal_available_at)->not->toBeNull()
        ->and($user->next_appeal_available_at)->toBeGreaterThan(now())
        ->and(now()->diffInDays($user->next_appeal_available_at, false))->toBeGreaterThanOrEqual(6)
        ->and(now()->diffInDays($user->next_appeal_available_at, false))->toBeLessThanOrEqual(8);
});

test('submitting appeal does not change brig_expires_at', function () {
    $originalBrigExpiry = now()->addDays(30);
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'brig_expires_at' => $originalBrigExpiry,
        'next_appeal_available_at' => null,
    ]);

    $this->actingAs($user);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', 'I would like to appeal this decision.')
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->brig_expires_at->timestamp)->toBe($originalBrigExpiry->timestamp);
});

test('appeal form is shown when user can appeal', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'brig_expires_at' => now()->addDays(30),
        'next_appeal_available_at' => now()->subHour(),
    ]);

    $this->actingAs($user);

    Volt::test('dashboard.in-brig-card')
        ->assertSee('Submit Appeal');
});

test('appeal requires message content', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'brig_expires_at' => now()->addDays(30),
        'next_appeal_available_at' => null,
    ]);

    $this->actingAs($user);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', '')
        ->call('submitAppeal')
        ->assertHasErrors(['appealMessage']);
});
