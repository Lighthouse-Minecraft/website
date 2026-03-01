<?php

declare(strict_types=1);

use App\Models\ReportCategory;

uses()->group('discipline-reports', 'policies');

it('allows admin to manage report categories', function () {
    $admin = loginAsAdmin();

    expect($admin->can('viewAny', ReportCategory::class))->toBeTrue()
        ->and($admin->can('create', ReportCategory::class))->toBeTrue();
});

it('allows command officer to manage report categories', function () {
    $officer = officerCommand();
    loginAs($officer);

    expect($officer->can('viewAny', ReportCategory::class))->toBeTrue()
        ->and($officer->can('create', ReportCategory::class))->toBeTrue();
});

it('allows officer to manage report categories', function () {
    $officer = officerQuartermaster();
    loginAs($officer);

    expect($officer->can('viewAny', ReportCategory::class))->toBeTrue()
        ->and($officer->can('create', ReportCategory::class))->toBeTrue();
});

it('prevents crew from managing report categories', function () {
    $crew = crewQuartermaster();
    loginAs($crew);

    expect($crew->can('viewAny', ReportCategory::class))->toBeFalse()
        ->and($crew->can('create', ReportCategory::class))->toBeFalse();
});

it('prevents deletion of report categories', function () {
    $admin = loginAsAdmin();
    $category = ReportCategory::factory()->create();

    // Admin before() returns true, so test the policy method directly
    $policy = new \App\Policies\ReportCategoryPolicy;
    expect($policy->delete($admin, $category))->toBeFalse();
});
