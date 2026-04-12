<?php

declare(strict_types=1);

use App\Services\VaultSession;

uses()->group('vault', 'services');

it('is locked by default', function () {
    $session = app(VaultSession::class);

    expect($session->isUnlocked())->toBeFalse();
});

it('is unlocked after calling unlock()', function () {
    $session = app(VaultSession::class);
    $session->unlock();

    expect($session->isUnlocked())->toBeTrue();
});

it('is locked after calling lock()', function () {
    $session = app(VaultSession::class);
    $session->unlock();
    $session->lock();

    expect($session->isUnlocked())->toBeFalse();
});

it('is locked when vault_unlocked_at is older than TTL', function () {
    $ttl = (int) config('vault.session_ttl_minutes', 30);
    $expiredTimestamp = now()->subMinutes($ttl + 1)->timestamp;

    session(['vault_unlocked_at' => $expiredTimestamp]);

    $session = app(VaultSession::class);

    expect($session->isUnlocked())->toBeFalse();
});

it('is unlocked when vault_unlocked_at is within TTL', function () {
    $ttl = (int) config('vault.session_ttl_minutes', 30);
    $recentTimestamp = now()->subMinutes($ttl - 1)->timestamp;

    session(['vault_unlocked_at' => $recentTimestamp]);

    $session = app(VaultSession::class);

    expect($session->isUnlocked())->toBeTrue();
});
