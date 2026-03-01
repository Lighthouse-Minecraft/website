<?php

declare(strict_types=1);

use App\Actions\PutUserInBrig;
use App\Enums\BrigType;
use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('parent-portal', 'actions', 'brig');

it('sets brig_type on user', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    PutUserInBrig::run($target, $admin, 'Test reason', brigType: BrigType::ParentalPending);

    expect($target->fresh()->brig_type)->toBe(BrigType::ParentalPending);
});

it('changes ParentDisabled MC accounts to Banned when brigged', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $account = MinecraftAccount::factory()->create([
        'user_id' => $target->id,
        'status' => MinecraftAccountStatus::ParentDisabled,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    PutUserInBrig::run($target, $admin, 'Discipline', brigType: BrigType::Discipline);

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Banned);
});

it('defaults to Discipline brig_type', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    PutUserInBrig::run($target, $admin, 'Test reason');

    expect($target->fresh()->brig_type)->toBe(BrigType::Discipline);
});

it('skips notification when notify is false', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    PutUserInBrig::run($target, $admin, 'Test reason', notify: false);

    Notification::assertNothingSent();
});
