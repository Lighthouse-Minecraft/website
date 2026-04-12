<?php

declare(strict_types=1);

use App\Services\VaultEncrypter;

uses()->group('vault', 'services');

it('encrypts and decrypts a string round-trip', function () {
    $encrypter = app(VaultEncrypter::class);

    $original = 'super-secret-password-123!';
    $encrypted = $encrypter->encrypt($original);

    expect($encrypted)->not->toBe($original)
        ->and($encrypter->decrypt($encrypted))->toBe($original);
});

it('produces different ciphertext for the same plaintext', function () {
    $encrypter = app(VaultEncrypter::class);

    $a = $encrypter->encrypt('same-value');
    $b = $encrypter->encrypt('same-value');

    expect($a)->not->toBe($b);
});

it('throws a RuntimeException when VAULT_KEY is not set', function () {
    config(['vault.key' => null]);

    new VaultEncrypter;
})->throws(RuntimeException::class);
