<?php

declare(strict_types=1);

use App\Actions\ReleaseUserFromBrig;
use App\Enums\BrigType;
use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('parent-portal', 'actions', 'brig');

it('re-brigs with ParentalDisabled when parent_allows_site is false', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $target = User::factory()->minor()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'parent_allows_site' => false,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Discipline release');

    $target->refresh();
    expect($target->in_brig)->toBeTrue()
        ->and($target->brig_type)->toBe(BrigType::ParentalDisabled);
});

it('restores MC to ParentDisabled when parent has MC disabled', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'parent_allows_minecraft' => false,
    ]);
    $account = MinecraftAccount::factory()->create([
        'user_id' => $target->id,
        'status' => MinecraftAccountStatus::Banned,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::ParentDisabled);
});

it('restores MC to Active when parent has MC enabled', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'parent_allows_minecraft' => true,
    ]);
    $account = MinecraftAccount::factory()->create([
        'user_id' => $target->id,
        'status' => MinecraftAccountStatus::Banned,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

it('fully releases when parent_allows_site is true', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'parent_allows_site' => true,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    $target->refresh();
    expect($target->in_brig)->toBeFalse()
        ->and($target->brig_type)->toBeNull();
});
