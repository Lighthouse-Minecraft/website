<?php

declare(strict_types=1);

use App\Actions\CreateCredential;
use App\Models\Credential;
use App\Models\User;

uses()->group('vault', 'actions');

it('creates a credential record', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Test Service',
        'username' => 'admin',
        'password' => 's3cr3t!',
    ]);

    expect($credential)->toBeInstanceOf(Credential::class)
        ->and($credential->name)->toBe('Test Service');
});

it('stores sensitive fields encrypted in the database', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Encrypt Test',
        'username' => 'myusername',
        'email' => 'admin@example.com',
        'password' => 'plaintext-password',
        'notes' => 'Some notes',
    ]);

    $raw = \Illuminate\Support\Facades\DB::table('credentials')->where('id', $credential->id)->first();

    expect($raw->username)->not->toBe('myusername')
        ->and($raw->email)->not->toBe('admin@example.com')
        ->and($raw->password)->not->toBe('plaintext-password')
        ->and($raw->notes)->not->toBe('Some notes');
});

it('decrypts sensitive fields correctly via model attributes', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Decrypt Test',
        'username' => 'myusername',
        'email' => 'admin@example.com',
        'password' => 'plaintext-password',
        'notes' => 'Some notes',
    ]);

    $fresh = $credential->fresh();

    expect($fresh->username)->toBe('myusername')
        ->and($fresh->email)->toBe('admin@example.com')
        ->and($fresh->password)->toBe('plaintext-password')
        ->and($fresh->notes)->toBe('Some notes');
});

it('sets created_by to the creator', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Test',
        'username' => 'user',
        'password' => 'pass',
    ]);

    expect($credential->created_by)->toBe($user->id);
});

it('defaults needs_password_change to false', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Test',
        'username' => 'user',
        'password' => 'pass',
    ]);

    expect($credential->needs_password_change)->toBeFalse();
});

it('records activity on creation', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Activity Test',
        'username' => 'user',
        'password' => 'pass',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => Credential::class,
        'subject_id' => $credential->id,
        'action' => 'credential_created',
    ]);
});

it('handles nullable optional fields', function () {
    $user = User::factory()->withRole('Vault Manager')->create();

    $credential = CreateCredential::run($user, [
        'name' => 'Minimal',
        'username' => 'admin',
        'password' => 'pass',
    ]);

    expect($credential->website_url)->toBeNull()
        ->and($credential->email)->toBeNull()
        ->and($credential->totp_secret)->toBeNull()
        ->and($credential->notes)->toBeNull()
        ->and($credential->recovery_codes)->toBeNull();
});
