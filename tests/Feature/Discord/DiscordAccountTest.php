<?php

declare(strict_types=1);

use App\Enums\DiscordAccountStatus;
use App\Models\DiscordAccount;
use App\Models\User;

uses()->group('discord');

it('belongs to a user', function () {
    $account = DiscordAccount::factory()->create();

    expect($account->user)->toBeInstanceOf(User::class);
});

it('user has many discord accounts', function () {
    $user = User::factory()->create();
    DiscordAccount::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->discordAccounts)->toHaveCount(2);
});

it('encrypts access token', function () {
    $account = DiscordAccount::factory()->create(['access_token' => 'my-secret-token']);

    // The raw DB value should NOT be the plain text
    $raw = \DB::table('discord_accounts')->where('id', $account->id)->value('access_token');
    expect($raw)->not->toBe('my-secret-token');

    // But the model should decrypt it
    expect($account->fresh()->access_token)->toBe('my-secret-token');
});

it('encrypts refresh token', function () {
    $account = DiscordAccount::factory()->create(['refresh_token' => 'my-refresh-token']);

    $raw = \DB::table('discord_accounts')->where('id', $account->id)->value('refresh_token');
    expect($raw)->not->toBe('my-refresh-token');

    expect($account->fresh()->refresh_token)->toBe('my-refresh-token');
});

it('casts status to enum', function () {
    $account = DiscordAccount::factory()->create();

    expect($account->status)->toBeInstanceOf(DiscordAccountStatus::class);
});

it('scopes active accounts', function () {
    $user = User::factory()->create();
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);
    DiscordAccount::factory()->brigged()->create(['user_id' => $user->id]);

    expect($user->discordAccounts()->active()->count())->toBe(1);
});

it('returns avatar url from cdn when hash exists', function () {
    $account = DiscordAccount::factory()->create([
        'discord_user_id' => '123456789',
        'avatar_hash' => 'abc123',
    ]);

    expect($account->avatarUrl())->toContain('cdn.discordapp.com/avatars/123456789/abc123');
});

it('returns default avatar url when no hash', function () {
    $account = DiscordAccount::factory()->create([
        'avatar_hash' => null,
    ]);

    expect($account->avatarUrl())->toContain('cdn.discordapp.com/embed/avatars/');
});

it('returns display name preferring global name', function () {
    $account = DiscordAccount::factory()->create([
        'username' => 'test_user',
        'global_name' => 'Test Display Name',
    ]);

    expect($account->displayName())->toBe('Test Display Name');
});

it('returns username when no global name', function () {
    $account = DiscordAccount::factory()->create([
        'username' => 'test_user',
        'global_name' => null,
    ]);

    expect($account->displayName())->toBe('test_user');
});

it('hasDiscordLinked returns true when user has active account', function () {
    $user = User::factory()->create();
    DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    expect($user->hasDiscordLinked())->toBeTrue();
});

it('hasDiscordLinked returns false when user has no accounts', function () {
    $user = User::factory()->create();

    expect($user->hasDiscordLinked())->toBeFalse();
});

it('hasDiscordLinked returns false when user only has brigged accounts', function () {
    $user = User::factory()->create();
    DiscordAccount::factory()->brigged()->create(['user_id' => $user->id]);

    expect($user->hasDiscordLinked())->toBeFalse();
});
