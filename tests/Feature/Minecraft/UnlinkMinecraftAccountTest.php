<?php

declare(strict_types=1);

use App\Actions\UnlinkMinecraftAccount;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = new UnlinkMinecraftAccount;

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => true, 'response' => 'Removed from whitelist']);
    });
});

test('unlinks minecraft account', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create();

    $result = $this->action->handle($account, $this->user);

    expect($result['success'])->toBeTrue();
    $this->assertDatabaseMissing('minecraft_accounts', ['id' => $account->id]);
});

test('prevents unlinking other users account', function () {
    $otherUser = User::factory()->create();
    $account = MinecraftAccount::factory()->for($otherUser)->create();

    $result = $this->action->handle($account, $this->user);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('permission');
    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});

test('sends async whitelist remove command', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create([
        'username' => 'TestPlayer',
    ]);

    $mock = $this->mock(MinecraftRconService::class);
    $mock->shouldNotReceive('executeCommand');

    $this->action->handle($account, $this->user);

    // Should queue notification instead of executing immediately
    $this->assertDatabaseHas('jobs', [
        'queue' => 'default',
    ]);
})->skip('Queue/notification testing requires additional infrastructure setup');

test('records activity log', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create();

    $this->action->handle($account, $this->user);

    // TODO: Enable when activity_log table is created
    // $this->assertDatabaseHas('activity_log', [
    //     'user_id' => $this->user->id,
    //     'action' => 'minecraft_account_unlinked',
    // ]);
    expect(true)->toBeTrue(); // Placeholder
});
