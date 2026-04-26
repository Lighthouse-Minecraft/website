<?php

declare(strict_types=1);

use App\Actions\AttachDisciplineReportImages;
use App\Models\DisciplineReport;
use App\Models\DisciplineReportImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses()->group('discipline-reports', 'evidence-ui');

beforeEach(function () {
    Storage::fake(config('filesystems.public_disk'));
});

// == view-report.blade.php: Evidence section visibility ==

it('view-report shows Evidence section with img elements when report has images', function () {
    $staff = officerCommand();
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create(); // draft — staff can view

    $file = UploadedFile::fake()->image('screenshot.jpg', 400, 300);
    AttachDisciplineReportImages::run($report, [$file]);

    $component = Volt::actingAs($staff)->test('reports.view-report', ['report' => $report]);

    $component->assertSee('Evidence');
    $component->assertSee('<img', false);
});

it('view-report does not render Evidence section when report has no images', function () {
    $staff = officerCommand();
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->published()->create();

    $component = Volt::actingAs($staff)->test('reports.view-report', ['report' => $report]);

    $component->assertDontSee('Evidence');
    $component->assertDontSee('<img', false);
});

// == discipline-reports-card: image UI visibility ==

it('opening edit modal for a published report is forbidden', function () {
    $staff = officerCommand();
    loginAs($staff);
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->published()->create();

    // Published reports cannot be edited — the update policy returns false
    // which means image add/remove controls are never accessible
    Volt::actingAs($staff)->test('users.discipline-reports-card', ['user' => $subject])
        ->call('openEditModal', $report->id)
        ->assertForbidden();
});

it('edit modal image UI is rendered for a draft report', function () {
    $staff = officerCommand();
    loginAs($staff);
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->byReporter($staff)->create(); // draft

    $component = Volt::actingAs($staff)->test('users.discipline-reports-card', ['user' => $subject]);
    $component->call('openEditModal', $report->id);

    $component->assertSee('Add More Evidence Images');
});

// == images attached during creation are persisted ==

it('images attached via AttachDisciplineReportImages are retrievable from the report', function () {
    $staff = officerCommand();
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    $files = [
        UploadedFile::fake()->image('evidence1.jpg', 300, 300),
        UploadedFile::fake()->image('evidence2.png', 300, 300),
    ];

    AttachDisciplineReportImages::run($report, $files);

    expect($report->images()->count())->toBe(2);
    Storage::disk(config('filesystems.public_disk'))->assertExists(
        $report->images()->first()->path
    );
});

// == cascade delete ==

it('deleting a report also removes its image files from disk', function () {
    $report = DisciplineReport::factory()->create();
    $file = UploadedFile::fake()->image('to-delete.jpg', 200, 200);

    AttachDisciplineReportImages::run($report, [$file]);

    $path = $report->images()->first()->path;
    Storage::disk(config('filesystems.public_disk'))->assertExists($path);

    $report->delete();

    Storage::disk(config('filesystems.public_disk'))->assertMissing($path);
    expect(DisciplineReportImage::where('discipline_report_id', $report->id)->count())->toBe(0);
});

// == removeImage tracks pending removal ==

it('removeImage marks an image ID for pending removal without immediately deleting', function () {
    $staff = officerCommand();
    loginAs($staff);
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->byReporter($staff)->create();

    $file = UploadedFile::fake()->image('existing.jpg', 200, 200);
    AttachDisciplineReportImages::run($report, [$file]);
    $image = $report->images()->first();

    $component = Volt::actingAs($staff)->test('users.discipline-reports-card', ['user' => $subject]);
    $component->call('openEditModal', $report->id);
    $component->call('removeImage', $image->id);

    // Image still exists in DB (not committed until updateReport is called)
    expect(DisciplineReportImage::find($image->id))->not->toBeNull();

    // But it's tracked in removedImageIds
    expect($component->get('removedImageIds'))->toContain($image->id);
});
