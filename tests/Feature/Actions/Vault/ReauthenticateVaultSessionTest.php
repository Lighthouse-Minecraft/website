<?php

declare(strict_types=1);

use App\Actions\ReauthenticateVaultSession;
use App\Models\User;
use App\Services\VaultSession;

uses()->group('vault', 'actions');

it('returns true and unlocks the session on correct password', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    $result = ReauthenticateVaultSession::run($user, 'correct-password');

    expect($result)->toBeTrue()
        ->and(app(VaultSession::class)->isUnlocked())->toBeTrue();
});

it('returns false and does not unlock the session on wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    $result = ReauthenticateVaultSession::run($user, 'wrong-password');

    expect($result)->toBeFalse()
        ->and(app(VaultSession::class)->isUnlocked())->toBeFalse();
});

it('does not modify the session when the password is wrong', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    // Ensure session is locked
    app(VaultSession::class)->lock();

    ReauthenticateVaultSession::run($user, 'wrong-password');

    expect(session('vault_unlocked_at'))->toBeNull();
});
