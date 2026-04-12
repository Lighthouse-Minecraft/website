<?php

declare(strict_types=1);

use App\Actions\CreateRuleVersion;
use App\Actions\UpdateRuleInDraft;
use App\Models\Rule;
use App\Models\RuleVersion;
use App\Models\User;

uses()->group('rules', 'actions');

it('creates a replacement rule with supersedes_rule_id pointing to the old rule', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $oldRule = Rule::factory()->create(['status' => 'active']);
    $draft->rules()->attach($oldRule->id, ['deactivate_on_publish' => false]);

    $replacement = UpdateRuleInDraft::run($draft, $oldRule, 'Updated Title', 'Updated description.', $user);

    expect($replacement->supersedes_rule_id)->toBe($oldRule->id)
        ->and($replacement->title)->toBe('Updated Title')
        ->and($replacement->status)->toBe('draft');
});

it('adds the replacement rule to the draft with deactivate_on_publish false', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $oldRule = Rule::factory()->create(['status' => 'active']);
    $draft->rules()->attach($oldRule->id, ['deactivate_on_publish' => false]);

    $replacement = UpdateRuleInDraft::run($draft, $oldRule, 'Updated Title', 'New text.', $user);

    $pivot = $draft->rules()->where('rules.id', $replacement->id)->first()?->pivot;
    expect($pivot)->not->toBeNull()
        ->and((bool) $pivot->deactivate_on_publish)->toBeFalse();
});

it('marks the old rule for deactivation on publish', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $oldRule = Rule::factory()->create(['status' => 'active']);
    $draft->rules()->attach($oldRule->id, ['deactivate_on_publish' => false]);

    UpdateRuleInDraft::run($draft, $oldRule, 'Updated Title', 'New text.', $user);

    $oldPivot = $draft->rules()->where('rules.id', $oldRule->id)->first()?->pivot;
    expect($oldPivot)->not->toBeNull()
        ->and((bool) $oldPivot->deactivate_on_publish)->toBeTrue();
});

it('does not immediately deactivate the old rule', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $oldRule = Rule::factory()->create(['status' => 'active']);
    $draft->rules()->attach($oldRule->id, ['deactivate_on_publish' => false]);

    UpdateRuleInDraft::run($draft, $oldRule, 'Updated Title', 'New text.', $user);

    expect($oldRule->fresh()->status)->toBe('active');
});

it('throws if the version is not a draft', function () {
    $user = User::factory()->create();
    $version = RuleVersion::factory()->create(['status' => 'submitted']);
    $oldRule = Rule::factory()->create(['status' => 'active']);

    UpdateRuleInDraft::run($version, $oldRule, 'Title', 'Desc.', $user);
})->throws(InvalidArgumentException::class);
