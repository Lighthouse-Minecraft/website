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

test('sends rank reset and whitelist remove commands via rcon', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create([
        'username' => 'TestPlayer',
    ]);

    $mock = $this->mock(MinecraftRconService::class);
    $mock->shouldReceive('executeCommand')
        ->once()
        ->with('lh setmember TestPlayer default', 'rank', 'TestPlayer', $this->user, \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'OK']);
    $mock->shouldReceive('executeCommand')
        ->once()
        ->with('whitelist remove TestPlayer', 'whitelist', 'TestPlayer', $this->user, \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Removed']);

    $this->action->handle($account, $this->user);
});

test('records activity log', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create();

    $this->action->handle($account, $this->user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => \App\Models\User::class,
        'subject_id' => $this->user->id,
        'action' => 'minecraft_account_unlinked',
    ]);
});
