<?php

declare(strict_types=1);

use App\Actions\CreateDisciplineReport;
use App\Actions\UpdateDisciplineReport;
use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\User;

uses()->group('discipline-reports', 'rules');

it('creates a discipline report with violated rules', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();
    $category = RuleCategory::first();

    $rule1 = Rule::create(['rule_category_id' => $category->id, 'title' => 'No Griefing', 'description' => 'Do not grief.', 'status' => 'active', 'sort_order' => 900]);
    $rule2 = Rule::create(['rule_category_id' => $category->id, 'title' => 'No Spamming', 'description' => 'Do not spam.', 'status' => 'active', 'sort_order' => 901]);

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident description',
        ReportLocation::Minecraft,
        'Verbal warning given',
        ReportSeverity::Minor,
        null,
        null,
        [$rule1->id, $rule2->id],
    );

    expect($report->violatedRules()->pluck('rules.id')->toArray())
        ->toContain($rule1->id)
        ->toContain($rule2->id);
});

it('creates a discipline report with no violated rules when none provided', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident description',
        ReportLocation::Minecraft,
        'Verbal warning given',
        ReportSeverity::Minor,
    );

    expect($report->violatedRules()->count())->toBe(0);
});

it('updates a discipline report with new violated rules', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();
    $category = RuleCategory::first();

    $rule1 = Rule::create(['rule_category_id' => $category->id, 'title' => 'No Griefing 2', 'description' => 'Do not grief.', 'status' => 'active', 'sort_order' => 910]);
    $rule2 = Rule::create(['rule_category_id' => $category->id, 'title' => 'No Spamming 2', 'description' => 'Do not spam.', 'status' => 'active', 'sort_order' => 911]);

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::Minecraft,
        'Warning given',
        ReportSeverity::Minor,
        null,
        null,
        [$rule1->id],
    );

    UpdateDisciplineReport::run(
        $report,
        $reporter,
        'Updated description',
        ReportLocation::Minecraft,
        'Warning given',
        ReportSeverity::Minor,
        null,
        null,
        [$rule2->id],
    );

    $ids = $report->fresh()->violatedRules()->pluck('rules.id')->toArray();
    expect($ids)->toContain($rule2->id)
        ->and($ids)->not->toContain($rule1->id);
});

it('clears violated rules when empty array passed on update', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();
    $category = RuleCategory::first();

    $rule = Rule::create(['rule_category_id' => $category->id, 'title' => 'No Hacking', 'description' => 'No hacks.', 'status' => 'active', 'sort_order' => 920]);

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::Minecraft,
        'Warning given',
        ReportSeverity::Minor,
        null,
        null,
        [$rule->id],
    );

    UpdateDisciplineReport::run(
        $report,
        $reporter,
        'Updated description',
        ReportLocation::Minecraft,
        'Warning given',
        ReportSeverity::Minor,
    );

    expect($report->fresh()->violatedRules()->count())->toBe(0);
});

it('violatedRules relationship returns rules with titles', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();
    $category = RuleCategory::first();

    $rule = Rule::create(['rule_category_id' => $category->id, 'title' => 'Respect Others', 'description' => 'Be respectful.', 'status' => 'active', 'sort_order' => 930]);

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident',
        ReportLocation::Minecraft,
        'Warning given',
        ReportSeverity::Minor,
        null,
        null,
        [$rule->id],
    );

    $violated = $report->violatedRules()->first();
    expect($violated->title)->toBe('Respect Others');
});
