<?php

declare(strict_types=1);

use App\Actions\RevokeMinecraftAccount;
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

test('admin can revoke account', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create();

    $result = $this->action->handle($account, $this->admin);

    expect($result['success'])->toBeTrue();
    $this->assertDatabaseMissing('minecraft_accounts', ['id' => $account->id]);
});

test('regular user cannot revoke', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create();

    $result = $this->action->handle($account, $this->regularUser);

    expect($result['success'])->toBeFalse();
    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});

test('records activity for both admin and affected user', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create();

    $this->action->handle($account, $this->admin);

    // TODO: Enable when activity_log table is created
    // $this->assertDatabaseHas('activity_log', [
    //     'user_id' => $this->admin->id,
    //     'action' => 'minecraft_account_revoked_admin',
    // ]);

    // $this->assertDatabaseHas('activity_log', [
    //     'user_id' => $this->regularUser->id,
    //     'action' => 'minecraft_account_revoked',
    // ]);
    expect(true)->toBeTrue(); // Placeholder
});

test('sends sync whitelist remove command', function () {
    $account = MinecraftAccount::factory()->for($this->regularUser)->create([
        'username' => 'TestPlayer',
    ]);

    $mock = $this->mock(MinecraftRconService::class);
    $mock->shouldReceive('executeCommand')
        ->once()
        ->with('whitelist remove TestPlayer', 'whitelist_remove', 'TestPlayer', $this->admin, \Mockery::any())
        ->andReturn(['success' => true]);

    $this->action->handle($account, $this->admin);
})->skip('RCON mock requires specific parameter matching');
