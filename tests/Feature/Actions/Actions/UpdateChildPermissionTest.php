<?php

declare(strict_types=1);

use App\Actions\UpdateChildPermission;
use App\Enums\BrigType;
use App\Enums\DiscordAccountStatus;
use App\Enums\MinecraftAccountStatus;
use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\ParentChildLink;
use App\Models\User;
use App\Services\DiscordApiService;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('parent-portal', 'actions');

// --- Site access ---

it('disables site access and puts child in parental brig', function () {
    Notification::fake();
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    UpdateChildPermission::run($child, $parent, 'use_site', false);

    $child->refresh();
    expect($child->parent_allows_site)->toBeFalse()
        ->and($child->in_brig)->toBeTrue()
        ->and($child->brig_type)->toBe(BrigType::ParentalDisabled);
});

it('enables site access and releases child from parental brig', function () {
    Notification::fake();
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalDisabled,
        'brig_reason' => 'Site access restricted by parent.',
        'parent_allows_site' => false,
    ]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    UpdateChildPermission::run($child, $parent, 'use_site', true);

    $child->refresh();
    expect($child->parent_allows_site)->toBeTrue()
        ->and($child->in_brig)->toBeFalse();
});

it('does not brig child for site disable if already in brig', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'brig_reason' => 'Bad behavior',
    ]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    UpdateChildPermission::run($child, $parent, 'use_site', false);

    $child->refresh();
    expect($child->parent_allows_site)->toBeFalse()
        ->and($child->brig_type)->toBe(BrigType::Discipline);
});

// --- Minecraft access ---

it('disables minecraft and sets active accounts to ParentDisabled', function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $child->id]);

    UpdateChildPermission::run($child, $parent, 'minecraft', false);

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::ParentDisabled)
        ->and($child->fresh()->parent_allows_minecraft)->toBeFalse();
});

it('enables minecraft and restores ParentDisabled accounts to Active', function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create(['parent_allows_minecraft' => false]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $account = MinecraftAccount::factory()->create([
        'user_id' => $child->id,
        'status' => MinecraftAccountStatus::ParentDisabled,
    ]);

    UpdateChildPermission::run($child, $parent, 'minecraft', true);

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Active)
        ->and($child->fresh()->parent_allows_minecraft)->toBeTrue();
});

// --- Discord access ---

it('disables discord and sets active accounts to ParentDisabled', function () {
    $this->mock(DiscordApiService::class, function ($mock) {
        $mock->shouldReceive('removeAllManagedRoles')->once();
    });

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $discordAccount = DiscordAccount::factory()->active()->create(['user_id' => $child->id]);

    UpdateChildPermission::run($child, $parent, 'discord', false);

    expect($discordAccount->fresh()->status)->toBe(DiscordAccountStatus::ParentDisabled)
        ->and($child->fresh()->parent_allows_discord)->toBeFalse();
});

it('enables discord and restores ParentDisabled accounts to Active', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create(['parent_allows_discord' => false]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $discordAccount = DiscordAccount::factory()->create([
        'user_id' => $child->id,
        'status' => DiscordAccountStatus::ParentDisabled,
    ]);

    UpdateChildPermission::run($child, $parent, 'discord', true);

    expect($discordAccount->fresh()->status)->toBe(DiscordAccountStatus::Active)
        ->and($child->fresh()->parent_allows_discord)->toBeTrue();
});

// --- Invalid permission ---

it('throws exception for unknown permission type', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();

    expect(fn () => UpdateChildPermission::run($child, $parent, 'invalid', true))
        ->toThrow(InvalidArgumentException::class);
});

// --- Activity logging ---

it('records activity when permission is changed', function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    UpdateChildPermission::run($child, $parent, 'minecraft', false);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $child->id,
        'action' => 'parent_permission_changed',
    ]);
});
