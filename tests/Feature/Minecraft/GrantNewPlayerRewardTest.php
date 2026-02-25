<?php

declare(strict_types=1);

use App\Actions\GrantNewPlayerReward;
use App\Models\MinecraftAccount;
use App\Models\MinecraftReward;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = MinecraftAccount::factory()->for($this->user)->active()->create([
        'username' => 'TestPlayer',
    ]);
});

test('grants reward when enabled and first account', function () {
    config([
        'lighthouse.minecraft.rewards.new_player_enabled' => true,
        'lighthouse.minecraft.rewards.new_player_diamonds' => 3,
        'lighthouse.minecraft.rewards.new_player_exchange_rate' => 32,
    ]);

    GrantNewPlayerReward::run($this->account, $this->user);

    $this->assertDatabaseHas('minecraft_rewards', [
        'user_id' => $this->user->id,
        'minecraft_account_id' => $this->account->id,
        'reward_name' => GrantNewPlayerReward::REWARD_NAME,
        'reward_description' => '96 Lumens',
    ]);
});

test('does not grant reward when disabled', function () {
    config([
        'lighthouse.minecraft.rewards.new_player_enabled' => false,
    ]);

    GrantNewPlayerReward::run($this->account, $this->user);

    $this->assertDatabaseMissing('minecraft_rewards', [
        'user_id' => $this->user->id,
    ]);
});

test('does not grant duplicate reward', function () {
    config([
        'lighthouse.minecraft.rewards.new_player_enabled' => true,
        'lighthouse.minecraft.rewards.new_player_diamonds' => 3,
        'lighthouse.minecraft.rewards.new_player_exchange_rate' => 32,
    ]);

    // Grant once
    GrantNewPlayerReward::run($this->account, $this->user);

    // Create a second account and try to grant again
    $secondAccount = MinecraftAccount::factory()->for($this->user)->active()->create([
        'username' => 'TestPlayer2',
    ]);

    GrantNewPlayerReward::run($secondAccount, $this->user);

    expect(MinecraftReward::where('user_id', $this->user->id)->count())->toBe(1);
});

test('calculates correct lumen amount from config', function () {
    config([
        'lighthouse.minecraft.rewards.new_player_enabled' => true,
        'lighthouse.minecraft.rewards.new_player_diamonds' => 5,
        'lighthouse.minecraft.rewards.new_player_exchange_rate' => 16,
    ]);

    GrantNewPlayerReward::run($this->account, $this->user);

    $this->assertDatabaseHas('minecraft_rewards', [
        'user_id' => $this->user->id,
        'reward_description' => '80 Lumens',
    ]);
});

test('records activity log entry', function () {
    config([
        'lighthouse.minecraft.rewards.new_player_enabled' => true,
        'lighthouse.minecraft.rewards.new_player_diamonds' => 3,
        'lighthouse.minecraft.rewards.new_player_exchange_rate' => 32,
    ]);

    GrantNewPlayerReward::run($this->account, $this->user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $this->user->id,
        'action' => 'minecraft_reward_granted',
    ]);
});

test('sends rcon money command', function () {
    Illuminate\Support\Facades\Notification::fake();

    config([
        'lighthouse.minecraft.rewards.new_player_enabled' => true,
        'lighthouse.minecraft.rewards.new_player_diamonds' => 3,
        'lighthouse.minecraft.rewards.new_player_exchange_rate' => 32,
    ]);

    GrantNewPlayerReward::run($this->account, $this->user);

    Illuminate\Support\Facades\Notification::assertSentOnDemand(
        App\Notifications\MinecraftCommandNotification::class,
        function ($notification) {
            return $notification->command === 'money give TestPlayer 96'
                && $notification->commandType === 'reward'
                && $notification->target === 'TestPlayer';
        }
    );
});
