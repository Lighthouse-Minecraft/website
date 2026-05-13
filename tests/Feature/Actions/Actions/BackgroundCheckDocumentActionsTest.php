<?php

declare(strict_types=1);

use App\Actions\AttachBackgroundCheckDocuments;
use App\Actions\DeleteBackgroundCheckDocument;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\SiteConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->group('background-checks', 'actions');

beforeEach(function () {
    Storage::fake(config('filesystems.public_disk'));
});

// === AttachBackgroundCheckDocuments ===

it('stores a PDF and creates a BackgroundCheckDocument record', function () {
    $check = BackgroundCheck::factory()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');

    AttachBackgroundCheckDocuments::run($check, [$file], $uploader);

    $doc = BackgroundCheckDocument::where('background_check_id', $check->id)->first();
    expect($doc)->not->toBeNull()
        ->and($doc->original_filename)->toBe('check.pdf')
        ->and($doc->uploaded_by_user_id)->toBe($uploader->id)
        ->and($doc->path)->toStartWith("background-checks/{$check->id}/");

    Storage::disk(config('filesystems.public_disk'))->assertExists($doc->path);
});

it('rejects a non-PDF file', function () {
    $check = BackgroundCheck::factory()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

    expect(fn () => AttachBackgroundCheckDocuments::run($check, [$file], $uploader))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects a file that exceeds the SiteConfig size limit', function () {
    SiteConfig::setValue('max_background_check_document_size_kb', '500');

    $check = BackgroundCheck::factory()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('check.pdf', 600, 'application/pdf');

    expect(fn () => AttachBackgroundCheckDocuments::run($check, [$file], $uploader))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('allows upload on an unlocked (Pending) check', function () {
    $check = BackgroundCheck::factory()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');

    AttachBackgroundCheckDocuments::run($check, [$file], $uploader);

    expect(BackgroundCheckDocument::where('background_check_id', $check->id)->count())->toBe(1);
});

it('allows upload on a locked (Passed) check', function () {
    $check = BackgroundCheck::factory()->passed()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');

    AttachBackgroundCheckDocuments::run($check, [$file], $uploader);

    expect(BackgroundCheckDocument::where('background_check_id', $check->id)->count())->toBe(1);
});

it('writes activity log on the parent BackgroundCheck when attaching a document', function () {
    $check = BackgroundCheck::factory()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');

    AttachBackgroundCheckDocuments::run($check, [$file], $uploader);

    expect(\App\Models\ActivityLog::where('subject_type', BackgroundCheck::class)
        ->where('subject_id', $check->id)
        ->where('action', 'background_check_document_attached')
        ->exists())->toBeTrue();
});

// === DeleteBackgroundCheckDocument ===

it('soft-deletes the document and removes the file from storage', function () {
    $check = BackgroundCheck::factory()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');

    AttachBackgroundCheckDocuments::run($check, [$file], $uploader);

    $doc = BackgroundCheckDocument::where('background_check_id', $check->id)->first();
    $path = $doc->path;

    Storage::disk(config('filesystems.public_disk'))->assertExists($path);

    $deleter = User::factory()->create();
    DeleteBackgroundCheckDocument::run($doc, $deleter);

    expect(BackgroundCheckDocument::find($doc->id))->toBeNull()
        ->and(BackgroundCheckDocument::withTrashed()->find($doc->id))->not->toBeNull();

    Storage::disk(config('filesystems.public_disk'))->assertMissing($path);
});

it('blocks deleting a document from a locked check', function () {
    $check = BackgroundCheck::factory()->passed()->create();
    $doc = BackgroundCheckDocument::factory()->create(['background_check_id' => $check->id]);
    $deleter = User::factory()->create();

    expect(fn () => DeleteBackgroundCheckDocument::run($doc, $deleter))
        ->toThrow(\InvalidArgumentException::class);
});

it('writes activity log on the parent BackgroundCheck when deleting a document', function () {
    $check = BackgroundCheck::factory()->create();
    $uploader = User::factory()->create();
    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');

    AttachBackgroundCheckDocuments::run($check, [$file], $uploader);

    $doc = BackgroundCheckDocument::where('background_check_id', $check->id)->first();
    $deleter = User::factory()->create();

    DeleteBackgroundCheckDocument::run($doc, $deleter);

    expect(\App\Models\ActivityLog::where('subject_type', BackgroundCheck::class)
        ->where('subject_id', $check->id)
        ->where('action', 'background_check_document_deleted')
        ->exists())->toBeTrue();
});
