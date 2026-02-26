<?php

declare(strict_types=1);

use App\Actions\RevokeMinecraftAccount;
use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->regularUser = User::factory()->create();
    $this->action = new RevokeMinecraftAccount;

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => true, 'response' => 'Removed from whitelist']);
    });
});

test('admin can revoke account by setting status to removed', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create();

    $result = $this->action->handle($account, $this->admin);

    expect($result['success'])->toBeTrue()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Removed);
    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});

test('regular user cannot revoke', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create();

    $result = $this->action->handle($account, $this->regularUser);

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});

test('records activity for affected user', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create();

    $this->action->handle($account, $this->admin);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $this->regularUser->id,
        'action' => 'minecraft_account_revoked',
    ]);
});

test('sends sync whitelist remove command', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create([
        'username' => 'TestPlayer',
    ]);

    $mock = $this->mock(MinecraftRconService::class);
    // Action makes two calls: rank reset, then whitelist remove
    $mock->shouldReceive('executeCommand')
        ->once()
        ->with('lh setmember TestPlayer default', 'rank', 'TestPlayer', $this->admin, \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'OK']);
    $mock->shouldReceive('executeCommand')
        ->once()
        ->with('whitelist remove TestPlayer', 'whitelist', 'TestPlayer', $this->admin, \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Removed']);

    $this->action->handle($account, $this->admin);
});
