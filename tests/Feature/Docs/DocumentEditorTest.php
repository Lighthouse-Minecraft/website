<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\DocumentationService;
use Illuminate\Support\Facades\File;

uses()->group('docs', 'editor');

function setupDocsForEditor(): string
{
    $tmpDir = sys_get_temp_dir().'/lighthouse-docs-editor-'.uniqid();
    mkdir($tmpDir.'/books/test-book/01-part/01-chapter', 0755, true);

    file_put_contents($tmpDir.'/books/test-book/_index.md', "---\ntitle: \"Test Book\"\nvisibility: public\norder: 1\n---\nContent.");
    file_put_contents($tmpDir.'/books/test-book/01-part/_index.md', "---\ntitle: \"Part One\"\norder: 1\n---\n");
    file_put_contents($tmpDir.'/books/test-book/01-part/01-chapter/_index.md', "---\ntitle: \"Chapter One\"\norder: 1\n---\n");
    file_put_contents($tmpDir.'/books/test-book/01-part/01-chapter/01-page.md', "---\ntitle: \"Test Page\"\nvisibility: public\norder: 1\nsummary: \"A test.\"\n---\nPage content.");

    return $tmpDir;
}

beforeEach(function () {
    $this->docsPath = setupDocsForEditor();
    app()->singleton(DocumentationService::class, function () {
        return new DocumentationService($this->docsPath);
    });
});

afterEach(function () {
    if (is_dir($this->docsPath)) {
        File::deleteDirectory($this->docsPath);
    }
});

it('shows editor index in local environment', function () {
    app()->detectEnvironment(fn () => 'local');
    $admin = loginAsAdmin();

    $this->get('/library/editor')
        ->assertOk()
        ->assertSee('Documentation Editor');
});

it('returns 404 for editor in non-local environment', function () {
    app()->detectEnvironment(fn () => 'production');
    $admin = loginAsAdmin();

    $this->get('/library/editor')
        ->assertNotFound();
});

it('requires authentication for editor', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/library/editor')
        ->assertRedirect();
});

it('requires edit-docs gate for editor', function () {
    app()->detectEnvironment(fn () => 'local');
    $user = User::factory()->create();
    loginAs($user);

    $this->get('/library/editor')
        ->assertForbidden();
});

it('saves edited front matter and body', function () {
    app()->detectEnvironment(fn () => 'local');
    $admin = loginAsAdmin();

    $service = app(DocumentationService::class);
    $service->savePage('books/test-book/01-part/01-chapter/01-page.md', [
        'title' => 'Updated Title',
        'visibility' => 'users',
        'order' => 2,
        'summary' => 'Updated summary.',
    ], 'Updated body content.');

    $result = $service->parseFile($this->docsPath.'/books/test-book/01-part/01-chapter/01-page.md');

    expect($result['meta']['title'])->toBe('Updated Title');
    expect($result['meta']['visibility'])->toBe('users');
    expect($result['body'])->toBe('Updated body content.');
});

it('creates a new page file', function () {
    app()->detectEnvironment(fn () => 'local');
    $admin = loginAsAdmin();

    $service = app(DocumentationService::class);
    $service->createPage('books/test-book/01-part/01-chapter', '02-new-page.md', [
        'title' => 'New Page',
        'visibility' => 'public',
        'order' => 2,
        'summary' => 'Brand new.',
    ], 'New content.');

    $filePath = $this->docsPath.'/books/test-book/01-part/01-chapter/02-new-page.md';
    expect(file_exists($filePath))->toBeTrue();

    $result = $service->parseFile($filePath);
    expect($result['meta']['title'])->toBe('New Page');
    expect($result['body'])->toBe('New content.');
});

it('rejects path traversal attempts', function () {
    $service = app(DocumentationService::class);

    expect($service->isValidDocsPath('../../etc/passwd'))->toBeFalse();
    expect($service->isValidDocsPath('../../../root/.ssh/id_rsa'))->toBeFalse();
    expect($service->isValidDocsPath('books/test-book/_index.md'))->toBeTrue();
});
