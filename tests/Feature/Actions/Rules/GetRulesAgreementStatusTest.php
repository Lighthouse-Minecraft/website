<?php

declare(strict_types=1);

use App\Actions\AddRuleToDraft;
use App\Actions\AgreeToRulesVersion;
use App\Actions\ApproveAndPublishVersion;
use App\Actions\CreateRuleVersion;
use App\Actions\GetRulesAgreementStatus;
use App\Actions\UpdateRuleInDraft;
use App\Enums\MembershipLevel;
use App\Models\RuleCategory;
use App\Models\User;

uses()->group('rules', 'actions', 'agreement');

it('returns has_agreed true when user has agreed to the current version', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    // Factory auto-creates agreement; verify it's there
    $status = GetRulesAgreementStatus::run($user);

    expect($status['has_agreed'])->toBeTrue();
});

it('returns has_agreed false when user has not agreed to the current version', function () {
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    $status = GetRulesAgreementStatus::run($user);

    expect($status['has_agreed'])->toBeFalse();
});

it('classifies rules as unchanged when the user has already agreed to them', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    // Factory auto-agrees to v1; now create a second version with the same rules
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);
    $draft->status = 'submitted';
    $draft->save();
    ApproveAndPublishVersion::run($draft, $approver);

    // User has agreed to v1 but not v2 yet — all rules carried over from v1 are 'unchanged'
    $status = GetRulesAgreementStatus::run($user);

    expect($status['has_agreed'])->toBeFalse();
    $allStatuses = $status['categories']->flatMap(fn ($cat) => $cat->rules->pluck('agreement_status'))->unique()->values()->all();
    expect($allStatuses)->toContain('unchanged')
        ->and($allStatuses)->not->toContain('new')
        ->and($allStatuses)->not->toContain('updated');
});

it('classifies a brand-new rule as new', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    AgreeToRulesVersion::run($user, $user);

    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);

    $category = RuleCategory::first();
    AddRuleToDraft::run($draft, $category, 'Brand New Rule', 'This rule is brand new.', $creator);

    $draft->status = 'submitted';
    $draft->save();
    ApproveAndPublishVersion::run($draft, $approver);

    $status = GetRulesAgreementStatus::run($user);

    $newRules = $status['categories']
        ->flatMap(fn ($cat) => $cat->rules)
        ->filter(fn ($r) => $r->agreement_status === 'new');

    expect($newRules->count())->toBe(1)
        ->and($newRules->first()->title)->toBe('Brand New Rule');
});

it('classifies a replacement rule as updated with the previous rule text', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    AgreeToRulesVersion::run($user, $user);

    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);

    // Update an existing active rule
    $activeRule = \App\Models\Rule::where('status', 'active')->first();
    UpdateRuleInDraft::run($draft, $activeRule, 'Updated Title', 'New updated description.', $creator);

    $draft->status = 'submitted';
    $draft->save();
    ApproveAndPublishVersion::run($draft, $approver);

    $status = GetRulesAgreementStatus::run($user);

    $updatedRules = $status['categories']
        ->flatMap(fn ($cat) => $cat->rules)
        ->filter(fn ($r) => $r->agreement_status === 'updated');

    expect($updatedRules->count())->toBe(1);
    $updated = $updatedRules->first();
    expect($updated->title)->toBe('Updated Title')
        ->and($updated->previous_rule)->not->toBeNull()
        ->and($updated->previous_rule->id)->toBe($activeRule->id);
});

it('returns has_agreed true when there is no published version', function () {
    // Clear all published versions
    \App\Models\RuleVersion::query()->update(['status' => 'draft']);

    $user = User::factory()->create();

    $status = GetRulesAgreementStatus::run($user);

    expect($status['has_agreed'])->toBeTrue()
        ->and($status['current_version'])->toBeNull();
});
