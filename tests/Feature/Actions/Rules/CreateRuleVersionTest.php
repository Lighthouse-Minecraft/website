<?php

declare(strict_types=1);

use App\Actions\CreateRuleVersion;
use App\Models\RuleVersion;
use App\Models\User;

uses()->group('rules', 'actions');

it('creates a draft version with the next version number', function () {
    $user = User::factory()->create();
    $published = RuleVersion::currentPublished();
    $expectedNumber = $published ? $published->version_number + 1 : 1;

    $draft = CreateRuleVersion::run($user);

    expect($draft->version_number)->toBe($expectedNumber)
        ->and($draft->status)->toBe('draft')
        ->and($draft->created_by_user_id)->toBe($user->id);
});

it('seeds the draft with all active rules from the published version', function () {
    $user = User::factory()->create();
    $published = RuleVersion::currentPublished();

    $draft = CreateRuleVersion::run($user);

    $publishedActiveRuleIds = $published->activeRules()->pluck('rules.id')->sort()->values();
    $draftActiveRuleIds = $draft->activeRules()->pluck('rules.id')->sort()->values();

    expect($draftActiveRuleIds->toArray())->toEqual($publishedActiveRuleIds->toArray());
});

it('persists the new draft to the database', function () {
    $user = User::factory()->create();

    $draft = CreateRuleVersion::run($user);

    $this->assertDatabaseHas('rule_versions', [
        'id' => $draft->id,
        'status' => 'draft',
        'created_by_user_id' => $user->id,
    ]);
});

it('only one draft can be created (no two draft versions exist at once)', function () {
    $user = User::factory()->create();

    $draft1 = CreateRuleVersion::run($user);

    expect($draft1->status)->toBe('draft');
    expect(RuleVersion::currentDraft())->not->toBeNull();
});
