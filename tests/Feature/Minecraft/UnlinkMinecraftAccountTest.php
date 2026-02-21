<?php

declare(strict_types=1);

use App\Actions\UnlinkMinecraftAccount;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Notifications\MinecraftCommandNotification;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

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
    Notification::fake();

    $account = MinecraftAccount::factory()->for($this->user)->create([
        'username' => 'TestPlayer',
    ]);

    // SendMinecraftCommand::dispatch() routes through an on-demand notification,
    // not the job queue. Notification::fake() is active globally, so RCON is never called.
    $this->mock(MinecraftRconService::class)->shouldNotReceive('executeCommand');

    $this->action->handle($account, $this->user);

    Notification::assertSentOnDemand(MinecraftCommandNotification::class);
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
