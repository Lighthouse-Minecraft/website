<?php

declare(strict_types=1);

use App\Actions\ReleaseChildToAdult;
use App\Enums\BrigType;
use App\Models\ParentChildLink;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('parent-portal', 'actions');

it('dissolves parent-child links', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor(18)->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    ReleaseChildToAdult::run($child, $parent);

    expect(ParentChildLink::where('child_user_id', $child->id)->exists())->toBeFalse();
});

it('resets parental toggles to defaults', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor(18)->create([
        'parent_allows_site' => false,
        'parent_allows_minecraft' => false,
        'parent_allows_discord' => false,
        'parent_email' => 'parent@example.com',
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    ReleaseChildToAdult::run($child, $parent);

    $child->refresh();
    expect($child->parent_allows_site)->toBeTrue()
        ->and($child->parent_allows_minecraft)->toBeTrue()
        ->and($child->parent_allows_discord)->toBeTrue()
        ->and($child->parent_email)->toBeNull();
});

it('releases from parental brig', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor(18)->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalPending,
        'brig_reason' => 'Pending parental approval',
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseChildToAdult::run($child, $parent);

    expect($child->fresh()->in_brig)->toBeFalse();
});

it('does not release from disciplinary brig', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor(18)->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'brig_reason' => 'Bad behavior',
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    ReleaseChildToAdult::run($child, $parent);

    expect($child->fresh()->in_brig)->toBeTrue();
});
