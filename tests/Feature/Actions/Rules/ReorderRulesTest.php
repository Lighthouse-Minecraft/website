<?php

declare(strict_types=1);

use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses()->group('rules', 'admin-ui');

it('moving a category down swaps sort_order with the next category', function () {
    $user = User::factory()->withRole('Rules - Manage')->create();

    $cat1 = RuleCategory::create(['name' => 'Cat A', 'sort_order' => 100]);
    $cat2 = RuleCategory::create(['name' => 'Cat B', 'sort_order' => 200]);

    actingAs($user);

    Volt::test('admin-manage-rules-page')
        ->call('moveCategoryDown', $cat1->id);

    expect($cat1->fresh()->sort_order)->toBe(200)
        ->and($cat2->fresh()->sort_order)->toBe(100);
});

it('moving a rule down swaps sort_order with the rule below', function () {
    $user = User::factory()->withRole('Rules - Manage')->create();

    $category = RuleCategory::first();
    $rule1 = Rule::create([
        'rule_category_id' => $category->id,
        'title' => 'Test Rule Above',
        'description' => 'Test',
        'status' => 'active',
        'sort_order' => 500,
    ]);
    $rule2 = Rule::create([
        'rule_category_id' => $category->id,
        'title' => 'Test Rule Below',
        'description' => 'Test',
        'status' => 'active',
        'sort_order' => 510,
    ]);

    actingAs($user);

    Volt::test('admin-manage-rules-page')
        ->call('moveRuleDown', $rule1->id);

    expect($rule1->fresh()->sort_order)->toBe(510)
        ->and($rule2->fresh()->sort_order)->toBe(500);
});

it('denies moveCategoryUp to user without rules.manage', function () {
    $user = User::factory()->create();

    $category = RuleCategory::first();

    actingAs($user);

    Volt::test('admin-manage-rules-page')
        ->call('moveCategoryUp', $category->id)
        ->assertForbidden();
});

it('denies startDraft to user without rules.manage', function () {
    $user = User::factory()->create();

    actingAs($user);

    Volt::test('admin-manage-rules-page')
        ->call('startDraft')
        ->assertForbidden();
});
