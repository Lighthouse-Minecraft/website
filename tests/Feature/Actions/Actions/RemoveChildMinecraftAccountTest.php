<?php

declare(strict_types=1);

use App\Actions\RemoveChildMinecraftAccount;
use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\ParentChildLink;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('parent-portal', 'actions');

it('removes an active minecraft account', function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->twice()->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $child->id]);

    $result = RemoveChildMinecraftAccount::run($parent, $account->id);

    expect($result['success'])->toBeTrue()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Removed);
});

it('rejects removal by non-parent', function () {
    $notParent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $child->id]);

    $result = RemoveChildMinecraftAccount::run($notParent, $account->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('permission');
});

it('rejects removal of non-active account', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $account = MinecraftAccount::factory()->create([
        'user_id' => $child->id,
        'status' => MinecraftAccountStatus::Banned,
    ]);

    $result = RemoveChildMinecraftAccount::run($parent, $account->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('current state');
});

it('fails gracefully when whitelist removal fails', function () {
    $rcon = $this->mock(MinecraftRconService::class);
    $rcon->shouldReceive('executeCommand')->once()->andReturn(['success' => false, 'response' => null, 'error' => 'Connection refused']);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $child->id]);

    $result = RemoveChildMinecraftAccount::run($parent, $account->id);

    expect($result['success'])->toBeFalse()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

it('records activity after removal', function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->twice()->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    $account = MinecraftAccount::factory()->active()->create(['user_id' => $child->id]);

    RemoveChildMinecraftAccount::run($parent, $account->id);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $child->id,
        'action' => 'minecraft_account_removed_by_parent',
    ]);
});
