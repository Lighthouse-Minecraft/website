<?php

declare(strict_types=1);

use App\Actions\AttachDisciplineReportImages;
use App\Models\DisciplineReport;
use App\Models\DisciplineReportImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->group('discipline-reports', 'actions');

beforeEach(function () {
    Storage::fake(config('filesystems.public_disk'));
});

it('stores files and creates DisciplineReportImage records for a draft report', function () {
    $report = DisciplineReport::factory()->create();
    $file = UploadedFile::fake()->image('screenshot.jpg', 400, 300);

    AttachDisciplineReportImages::run($report, [$file]);

    $image = DisciplineReportImage::where('discipline_report_id', $report->id)->first();

    expect($image)->not->toBeNull()
        ->and($image->original_filename)->toBe('screenshot.jpg')
        ->and($image->path)->toStartWith("report-evidence/{$report->id}/");

    Storage::disk(config('filesystems.public_disk'))->assertExists($image->path);
});

it('creates multiple image records when multiple files are provided', function () {
    $report = DisciplineReport::factory()->create();
    $files = [
        UploadedFile::fake()->image('shot1.jpg', 200, 200),
        UploadedFile::fake()->image('shot2.png', 200, 200),
    ];

    AttachDisciplineReportImages::run($report, $files);

    expect(DisciplineReportImage::where('discipline_report_id', $report->id)->count())->toBe(2);
});

it('throws an exception when attaching images to a published report', function () {
    $report = DisciplineReport::factory()->published()->create();
    $file = UploadedFile::fake()->image('screenshot.jpg', 200, 200);

    expect(fn () => AttachDisciplineReportImages::run($report, [$file]))
        ->toThrow(\RuntimeException::class);
});

it('removes file from disk when a DisciplineReportImage record is deleted', function () {
    $report = DisciplineReport::factory()->create();
    $file = UploadedFile::fake()->image('evidence.jpg', 200, 200);

    AttachDisciplineReportImages::run($report, [$file]);

    $image = DisciplineReportImage::where('discipline_report_id', $report->id)->first();
    $path = $image->path;

    Storage::disk(config('filesystems.public_disk'))->assertExists($path);

    $image->delete();

    Storage::disk(config('filesystems.public_disk'))->assertMissing($path);
});
