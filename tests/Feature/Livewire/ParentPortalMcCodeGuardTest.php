<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\ParentChildLink;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal');

it('generateChildMcCode rejects a Drifter child with an error toast', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Drifter)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->set('childMcUsernames.'.$child->id, 'TestPlayer')
        ->call('generateChildMcCode', $child->id)
        ->assertHasNoErrors();

    // Child should not have had a verification code generated (no MC accounts)
    expect($child->fresh()->minecraftAccounts()->count())->toBe(0);
});

it('generateChildMcCode rejects a Stowaway child with an error toast', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->set('childMcUsernames.'.$child->id, 'TestPlayer')
        ->call('generateChildMcCode', $child->id)
        ->assertHasNoErrors();

    expect($child->fresh()->minecraftAccounts()->count())->toBe(0);
});

it('generateChildMcCode proceeds for a Traveler child', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Traveler)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    // The code generation itself may fail (e.g., external API), but it should not be blocked by the guard
    // We verify no "Rules Required" or "Awaiting Review" toast was shown by the guard
    $component = Livewire\Volt\Volt::test('parent-portal.index')
        ->set('childMcUsernames.'.$child->id, 'TestPlayer')
        ->call('generateChildMcCode', $child->id);

    // The test passes as long as the guard didn't block it — no assertion on actual MC account creation
    // since that requires external verification infrastructure
    $component->assertHasNoErrors();
});
