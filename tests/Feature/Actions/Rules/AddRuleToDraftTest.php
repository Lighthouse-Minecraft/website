<?php

declare(strict_types=1);

use App\Actions\AddRuleToDraft;
use App\Actions\CreateRuleVersion;
use App\Models\RuleCategory;
use App\Models\User;

uses()->group('rules', 'actions');

it('creates a rule in draft status linked to the version', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $category = RuleCategory::first();

    $rule = AddRuleToDraft::run($draft, $category, 'New Rule', 'Rule description.', $user);

    expect($rule->status)->toBe('draft')
        ->and($rule->title)->toBe('New Rule')
        ->and($rule->description)->toBe('Rule description.')
        ->and($rule->rule_category_id)->toBe($category->id)
        ->and($rule->created_by_user_id)->toBe($user->id);
});

it('links the new rule to the draft version with deactivate_on_publish false', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $category = RuleCategory::first();

    $rule = AddRuleToDraft::run($draft, $category, 'New Rule', 'Description.', $user);

    $pivot = $draft->rules()->where('rules.id', $rule->id)->first()?->pivot;

    expect($pivot)->not->toBeNull()
        ->and((bool) $pivot->deactivate_on_publish)->toBeFalse();
});

it('persists the rule to the database', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $category = RuleCategory::first();

    $rule = AddRuleToDraft::run($draft, $category, 'Persisted Rule', 'Some text.', $user);

    $this->assertDatabaseHas('rules', [
        'id' => $rule->id,
        'title' => 'Persisted Rule',
        'status' => 'draft',
    ]);
});
