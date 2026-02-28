<?php

declare(strict_types=1);

use App\Actions\ForceDeleteMinecraftAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->regularUser = User::factory()->create();
    $this->action = new ForceDeleteMinecraftAccount;
});

test('admin can permanently delete a removed account', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->removed()->create();

    $result = $this->action->handle($account, $this->admin);

    expect($result['success'])->toBeTrue();
    $this->assertDatabaseMissing('minecraft_accounts', ['id' => $account->id]);
});

test('regular user cannot permanently delete', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->removed()->create();

    $result = $this->action->handle($account, $this->regularUser);

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});

test('cannot permanently delete an active account', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->active()->create();

    $result = $this->action->handle($account, $this->admin);

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});

test('records activity log for permanent deletion', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->removed()->create();

    $this->action->handle($account, $this->admin);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $this->regularUser->id,
        'action' => 'minecraft_account_permanently_deleted',
    ]);
});

test('releases UUID so it can be re-registered', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->removed()->create();
    $uuid = $account->uuid;

    $this->action->handle($account, $this->admin);

    // UUID should no longer exist in the database
    $this->assertDatabaseMissing('minecraft_accounts', ['uuid' => $uuid]);
});
