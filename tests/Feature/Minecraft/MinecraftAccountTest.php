<?php

declare(strict_types=1);

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\User;

test('belongs to user', function () {
    $user = User::factory()->create();
    $account = MinecraftAccount::factory()->for($user)->create();

    expect($account->user)->toBeInstanceOf(User::class)
        ->and($account->user->id)->toBe($user->id);
});

test('casts account type to enum', function () {
    $account = MinecraftAccount::factory()->java()->create();

    expect($account->account_type)->toBeInstanceOf(MinecraftAccountType::class)
        ->and($account->account_type)->toBe(MinecraftAccountType::Java);
});

test('casts timestamps correctly', function () {
    $account = MinecraftAccount::factory()->create();

    expect($account->verified_at)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($account->last_username_check_at)->toBeNull()
        ->and($account->created_at)->toBeInstanceOf(Carbon\Carbon::class);
});

test('can create java account', function () {
    $account = MinecraftAccount::factory()->java()->create();

    expect($account->account_type)->toBe(MinecraftAccountType::Java)
        ->and($account->username)->toMatch('/^[A-Za-z0-9_]{3,16}$/');
});

test('can create bedrock account', function () {
    $account = MinecraftAccount::factory()->bedrock()->create();

    expect($account->account_type)->toBe(MinecraftAccountType::Bedrock)
        ->and($account->username)->toStartWith('.');
});
