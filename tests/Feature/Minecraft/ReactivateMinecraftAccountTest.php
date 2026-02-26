<?php

declare(strict_types=1);

use App\Actions\ReactivateMinecraftAccount;
use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = new ReactivateMinecraftAccount;

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => true, 'response' => 'Added to whitelist']);
    });
});

test('reactivates a removed account back to active', function () {
    $account = MinecraftAccount::factory()->for($this->user)->removed()->create();

    $result = $this->action->handle($account, $this->user);

    expect($result['success'])->toBeTrue()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

test('fails if account is not in removed status', function () {
    $account = MinecraftAccount::factory()->for($this->user)->active()->create();

    $result = $this->action->handle($account, $this->user);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('removed');
});

test('fails if user has reached max account limit', function () {
    $max = config('lighthouse.max_minecraft_accounts');
    MinecraftAccount::factory()->for($this->user)->active()->count($max)->create();

    $removedAccount = MinecraftAccount::factory()->for($this->user)->removed()->create();

    $result = $this->action->handle($removedAccount, $this->user);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('limit');
});

test('fails if user is in the brig', function () {
    $brigUser = User::factory()->create(['in_brig' => true]);
    $account = MinecraftAccount::factory()->for($brigUser)->removed()->create();

    $result = $this->action->handle($account, $brigUser);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('brig');
});

test('fails if whitelist add command fails', function () {
    $account = MinecraftAccount::factory()->for($this->user)->removed()->create();

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => false, 'response' => 'Server unreachable']);
    });

    $result = $this->action->handle($account, $this->user);

    expect($result['success'])->toBeFalse()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Removed);
});

test('records activity log on reactivation', function () {
    $account = MinecraftAccount::factory()->for($this->user)->removed()->create();

    $this->action->handle($account, $this->user);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $this->user->id,
        'action' => 'minecraft_account_reactivated',
    ]);
});
