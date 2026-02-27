<?php

declare(strict_types=1);

use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

uses()->group('avatar');

describe('User::avatarUrl()', function () {
    it('returns null when auto preference and no linked accounts', function () {
        $user = User::factory()->create(['avatar_preference' => 'auto']);

        expect($user->avatarUrl())->toBeNull();
    });

    it('returns minecraft avatar in auto mode when MC account has avatar', function () {
        $user = User::factory()->create(['avatar_preference' => 'auto']);
        MinecraftAccount::factory()->active()->create([
            'user_id' => $user->id,
            'avatar_url' => 'https://crafatar.com/avatars/test-uuid',
        ]);

        expect($user->avatarUrl())->toBe('https://crafatar.com/avatars/test-uuid');
    });

    it('falls back to discord in auto mode when no MC avatar', function () {
        $user = User::factory()->create(['avatar_preference' => 'auto']);
        DiscordAccount::factory()->active()->create([
            'user_id' => $user->id,
            'discord_user_id' => '123456789012345678',
            'avatar_hash' => 'abc123',
        ]);

        expect($user->avatarUrl())->toContain('cdn.discordapp.com');
    });

    it('prefers minecraft over discord in auto mode', function () {
        $user = User::factory()->create(['avatar_preference' => 'auto']);
        MinecraftAccount::factory()->active()->create([
            'user_id' => $user->id,
            'avatar_url' => 'https://crafatar.com/avatars/test-uuid',
        ]);
        DiscordAccount::factory()->active()->create([
            'user_id' => $user->id,
            'discord_user_id' => '123456789012345678',
            'avatar_hash' => 'abc123',
        ]);

        expect($user->avatarUrl())->toBe('https://crafatar.com/avatars/test-uuid');
    });

    it('returns minecraft avatar when preference is minecraft', function () {
        $user = User::factory()->create(['avatar_preference' => 'minecraft']);
        MinecraftAccount::factory()->active()->create([
            'user_id' => $user->id,
            'avatar_url' => 'https://crafatar.com/avatars/test-uuid',
        ]);

        expect($user->avatarUrl())->toBe('https://crafatar.com/avatars/test-uuid');
    });

    it('returns null when preference is minecraft but no MC account', function () {
        $user = User::factory()->create(['avatar_preference' => 'minecraft']);

        expect($user->avatarUrl())->toBeNull();
    });

    it('returns discord avatar when preference is discord', function () {
        $user = User::factory()->create(['avatar_preference' => 'discord']);
        DiscordAccount::factory()->active()->create([
            'user_id' => $user->id,
            'discord_user_id' => '123456789012345678',
            'avatar_hash' => 'abc123',
        ]);

        expect($user->avatarUrl())->toContain('cdn.discordapp.com');
    });

    it('returns gravatar URL when preference is gravatar', function () {
        $user = User::factory()->create([
            'avatar_preference' => 'gravatar',
            'email' => 'test@example.com',
        ]);
        $expected = 'https://www.gravatar.com/avatar/'.md5('test@example.com').'?d=mp&s=64';

        expect($user->avatarUrl())->toBe($expected);
    });

    it('skips inactive minecraft accounts in auto mode', function () {
        $user = User::factory()->create(['avatar_preference' => 'auto']);
        MinecraftAccount::factory()->removed()->create([
            'user_id' => $user->id,
            'avatar_url' => 'https://crafatar.com/avatars/test-uuid',
        ]);

        expect($user->avatarUrl())->toBeNull();
    });

    it('defaults to auto behavior for new users', function () {
        $user = User::factory()->create();
        $user->refresh();

        // Default is 'auto', so with no linked accounts, returns null
        expect($user->avatar_preference)->toBe('auto')
            ->and($user->avatarUrl())->toBeNull();
    });
});
