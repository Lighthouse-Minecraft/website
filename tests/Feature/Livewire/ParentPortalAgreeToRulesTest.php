<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\ParentChildLink;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal');

// ─── Blade state rendering ────────────────────────────────────────────────────

it('shows the Drifter rules agreement card when child is Drifter and Minecraft is enabled', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Drifter)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->assertSee('Rules Agreement Required')
        ->assertSee('I agree to the community rules on behalf of');
});

it('shows the Stowaway waiting card when child is Stowaway and Minecraft is enabled', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->assertSee('Awaiting Staff Review')
        ->assertDontSee('I agree to the community rules on behalf of');
});

it('shows the Minecraft linking UI for a Traveler child', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Traveler)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->assertSee('Link Minecraft Account')
        ->assertDontSee('Rules Agreement Required')
        ->assertDontSee('Awaiting Staff Review');
});

it('shows the Drifter rules agreement card even when Minecraft is disabled', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Drifter)->create([
        'parent_allows_minecraft' => false,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->assertSee('Rules Agreement Required')
        ->assertSee('I agree to the community rules on behalf of');
});

// ─── agreeToRulesOnBehalf method ─────────────────────────────────────────────

it('parent can agree to rules on behalf of a Drifter child', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Drifter)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->call('agreeToRulesOnBehalf', $child->id);

    $fresh = $child->fresh();
    expect($fresh->membership_level)->toBe(MembershipLevel::Stowaway)
        ->and($fresh->rules_accepted_by_user_id)->toBe($parent->id);
});

it('parent agreement sets rules_accepted_by_user_id to parent id, not child id', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Drifter)->create([
        'parent_allows_minecraft' => true,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->call('agreeToRulesOnBehalf', $child->id);

    expect($child->fresh()->rules_accepted_by_user_id)->toBe($parent->id)
        ->and($child->fresh()->rules_accepted_by_user_id)->not->toBe($child->id);
});

it('cannot agree on behalf of an already-Stowaway child', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'parent_allows_minecraft' => true,
        'rules_accepted_by_user_id' => null,
    ]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->call('agreeToRulesOnBehalf', $child->id);

    expect($child->fresh()->rules_accepted_by_user_id)->toBeNull();
});

it('cannot agree on behalf of an unrelated child', function () {
    $parent = User::factory()->adult()->create();
    $unrelatedChild = User::factory()->minor()->withMembershipLevel(MembershipLevel::Drifter)->create([
        'parent_allows_minecraft' => true,
    ]);
    // No ParentChildLink — $unrelatedChild is not $parent's child
    actingAs($parent);

    // Add a real child so the portal loads
    $realChild = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $realChild->id]);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->call('agreeToRulesOnBehalf', $unrelatedChild->id);

    expect($unrelatedChild->fresh()->rules_accepted_by_user_id)->toBeNull();
});
