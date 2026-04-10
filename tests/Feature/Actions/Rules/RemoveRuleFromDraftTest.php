<?php

declare(strict_types=1);

use App\Actions\AddRuleToDraft;
use App\Actions\CreateRuleVersion;
use App\Actions\RemoveRuleFromDraft;
use App\Actions\UpdateRuleInDraft;
use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

uses()->group('rules', 'actions');

it('removes a draft rule from the version and deletes it', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $category = RuleCategory::first();
    $rule = AddRuleToDraft::run($draft, $category, 'Temp Rule', 'Temporary.', $user);

    RemoveRuleFromDraft::run($draft, $rule);

    $this->assertDatabaseMissing('rules', ['id' => $rule->id]);
    $this->assertDatabaseMissing('rule_version_rules', ['rule_id' => $rule->id]);
});

it('removes the deactivation mark on the superseded rule when removing a replacement', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $category = RuleCategory::first();
    $activeRule = Rule::where('status', 'active')->first();

    $replacement = UpdateRuleInDraft::run($draft, $activeRule, 'Updated Title', 'Updated description.', $user);

    // Confirm the active rule is marked for deactivation in the draft
    $pivot = $draft->rules()->where('rules.id', $activeRule->id)->first()?->pivot;
    expect((bool) $pivot->deactivate_on_publish)->toBeTrue();

    RemoveRuleFromDraft::run($draft, $replacement);

    // The replacement rule is gone
    $this->assertDatabaseMissing('rules', ['id' => $replacement->id]);

    // The active rule is no longer in the draft at all
    expect($draft->rules()->where('rules.id', $activeRule->id)->exists())->toBeFalse();
});

it('throws when the draft is not in draft status', function () {
    $user = User::factory()->create();
    $category = RuleCategory::first();

    $submittedVersion = RuleVersion::create([
        'version_number' => 99,
        'status' => 'submitted',
        'created_by_user_id' => $user->id,
    ]);

    $rule = Rule::factory()->create([
        'rule_category_id' => $category->id,
        'status' => 'draft',
        'created_by_user_id' => $user->id,
    ]);

    expect(fn () => RemoveRuleFromDraft::run($submittedVersion, $rule))
        ->toThrow(AuthorizationException::class);
});

it('throws when the rule is not in draft status', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $activeRule = Rule::where('status', 'active')->first();

    expect(fn () => RemoveRuleFromDraft::run($draft, $activeRule))
        ->toThrow(AuthorizationException::class);
});
