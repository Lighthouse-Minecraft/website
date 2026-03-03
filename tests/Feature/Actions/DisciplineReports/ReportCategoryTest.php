<?php

declare(strict_types=1);

use App\Actions\CreateDisciplineReport;
use App\Actions\UpdateDisciplineReport;
use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Models\DisciplineReport;
use App\Models\ReportCategory;
use App\Models\User;

uses()->group('discipline-reports', 'actions');

it('creates a report with a category', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();
    $category = ReportCategory::factory()->create(['name' => 'Harassment']);

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident with category',
        ReportLocation::Minecraft,
        'Verbal warning given',
        ReportSeverity::Minor,
        null,
        $category,
    );

    expect($report->report_category_id)->toBe($category->id)
        ->and($report->category->name)->toBe('Harassment');
});

it('creates a report without a category', function () {
    $reporter = officerCommand();
    $subject = User::factory()->create();

    $report = CreateDisciplineReport::run(
        $subject,
        $reporter,
        'Test incident without category',
        ReportLocation::Minecraft,
        'Verbal warning given',
        ReportSeverity::Minor,
    );

    expect($report->report_category_id)->toBeNull();
});

it('updates a report category', function () {
    $reporter = officerCommand();
    $category = ReportCategory::factory()->create(['name' => 'Griefing']);
    $report = DisciplineReport::factory()->byReporter($reporter)->create();

    $updated = UpdateDisciplineReport::run(
        $report,
        $reporter,
        'Updated description text',
        ReportLocation::Minecraft,
        'Updated actions taken',
        ReportSeverity::Minor,
        null,
        $category,
    );

    expect($updated->report_category_id)->toBe($category->id);
});
