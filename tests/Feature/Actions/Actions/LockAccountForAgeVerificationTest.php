<?php

declare(strict_types=1);

use App\Actions\LockAccountForAgeVerification;
use App\Enums\BrigType;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('parent-portal', 'actions');

it('puts user in brig with age_lock type', function () {
    $admin = User::factory()->create();
    $target = User::factory()->adult()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    LockAccountForAgeVerification::run($target, $admin);

    $target->refresh();
    expect($target->in_brig)->toBeTrue()
        ->and($target->brig_type)->toBe(BrigType::AgeLock);
});

it('clears date_of_birth', function () {
    $admin = User::factory()->create();
    $target = User::factory()->adult()->create();

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    LockAccountForAgeVerification::run($target, $admin);

    expect($target->fresh()->date_of_birth)->toBeNull();
});

it('updates existing brig to age_lock type', function () {
    $admin = User::factory()->create();
    $target = User::factory()->adult()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'brig_reason' => 'Previous reason',
    ]);

    LockAccountForAgeVerification::run($target, $admin);

    $target->refresh();
    expect($target->brig_type)->toBe(BrigType::AgeLock)
        ->and($target->date_of_birth)->toBeNull();
});
