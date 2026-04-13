<?php

declare(strict_types=1);

use App\Actions\CreateCredential;
use App\Actions\GenerateTotpCode;
use App\Models\User;

uses()->group('vault', 'actions');

it('returns a 6-digit TOTP code', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    // Use a well-known base32 TOTP secret
    $credential = CreateCredential::run($user, [
        'name' => 'TOTP Test',
        'username' => 'admin',
        'password' => 'pass',
        'totp_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $result = GenerateTotpCode::run($credential);

    expect($result['code'])->toMatch('/^\d{6}$/')
        ->and($result['seconds_remaining'])->toBeGreaterThanOrEqual(1)
        ->and($result['seconds_remaining'])->toBeLessThanOrEqual(30);
});

it('does not include the raw TOTP secret in the return value', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = CreateCredential::run($user, [
        'name' => 'TOTP Secret Test',
        'username' => 'admin',
        'password' => 'pass',
        'totp_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $result = GenerateTotpCode::run($credential);

    expect($result)->not->toHaveKey('secret')
        ->and(array_keys($result))->toEqual(['code', 'seconds_remaining']);
});
