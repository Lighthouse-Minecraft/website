<?php

declare(strict_types=1);

use App\Actions\CreateRuleVersion;
use App\Actions\DeactivateRuleInDraft;
use App\Models\Rule;
use App\Models\User;

uses()->group('rules', 'actions');

it('marks the rule for deactivation on publish', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $rule = Rule::where('status', 'active')->first();

    DeactivateRuleInDraft::run($draft, $rule);

    $pivot = $draft->rules()->where('rules.id', $rule->id)->first()?->pivot;
    expect($pivot)->not->toBeNull()
        ->and((bool) $pivot->deactivate_on_publish)->toBeTrue();
});

it('does not immediately deactivate the rule', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $rule = Rule::where('status', 'active')->first();

    DeactivateRuleInDraft::run($draft, $rule);

    expect($rule->fresh()->status)->toBe('active');
});

it('adds the rule to the draft version if not already present', function () {
    $user = User::factory()->create();

    // Create a draft without seeding (fresh version, no published to copy from)
    $draft = \App\Models\RuleVersion::create([
        'version_number' => 99,
        'status' => 'draft',
        'created_by_user_id' => $user->id,
    ]);
    $rule = Rule::where('status', 'active')->first();

    DeactivateRuleInDraft::run($draft, $rule);

    $this->assertDatabaseHas('rule_version_rules', [
        'rule_version_id' => $draft->id,
        'rule_id' => $rule->id,
        'deactivate_on_publish' => true,
    ]);
});
