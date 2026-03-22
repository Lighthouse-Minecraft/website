<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;
use App\Services\DocumentationService;
use Illuminate\Support\Facades\File;

uses()->group('docs', 'livewire');

function setupDocsForViewer(): string
{
    $tmpDir = sys_get_temp_dir().'/lighthouse-docs-viewer-'.uniqid();
    mkdir($tmpDir.'/books/test-book/01-part/01-chapter', 0755, true);
    mkdir($tmpDir.'/guides/test-guide', 0755, true);

    file_put_contents($tmpDir.'/books/test-book/_index.md', "---\ntitle: \"Test Book\"\nvisibility: public\norder: 1\nsummary: \"A book.\"\n---\nBook content.");
    file_put_contents($tmpDir.'/books/test-book/01-part/_index.md', "---\ntitle: \"Part One\"\norder: 1\n---\n");
    file_put_contents($tmpDir.'/books/test-book/01-part/01-chapter/_index.md', "---\ntitle: \"Chapter One\"\norder: 1\n---\n");
    file_put_contents($tmpDir.'/books/test-book/01-part/01-chapter/01-public-page.md', "---\ntitle: \"Public Page\"\nvisibility: public\norder: 1\n---\nPublic content here.");
    file_put_contents($tmpDir.'/books/test-book/01-part/01-chapter/02-users-page.md', "---\ntitle: \"Users Page\"\nvisibility: users\norder: 2\n---\nUsers only content.");
    file_put_contents($tmpDir.'/books/test-book/01-part/01-chapter/03-staff-page.md', "---\ntitle: \"Staff Page\"\nvisibility: staff\norder: 3\n---\nStaff only content.");

    // Staff-only book
    mkdir($tmpDir.'/books/staff-book/01-part/01-chapter', 0755, true);
    file_put_contents($tmpDir.'/books/staff-book/_index.md', "---\ntitle: \"Staff Book\"\nvisibility: staff\norder: 2\nsummary: \"Staff only.\"\n---\n");
    file_put_contents($tmpDir.'/books/staff-book/01-part/_index.md', "---\ntitle: \"Part\"\norder: 1\n---\n");
    file_put_contents($tmpDir.'/books/staff-book/01-part/01-chapter/_index.md', "---\ntitle: \"Chapter\"\norder: 1\n---\n");
    file_put_contents($tmpDir.'/books/staff-book/01-part/01-chapter/01-page.md', "---\ntitle: \"Staff Content\"\norder: 1\n---\nSecret.");

    file_put_contents($tmpDir.'/guides/test-guide/_index.md', "---\ntitle: \"Test Guide\"\nvisibility: public\norder: 1\nsummary: \"A guide.\"\n---\n");
    file_put_contents($tmpDir.'/guides/test-guide/01-step.md', "---\ntitle: \"Step One\"\norder: 1\n---\nStep content.");

    return $tmpDir;
}

beforeEach(function () {
    $this->docsPath = setupDocsForViewer();
    app()->singleton(DocumentationService::class, function () {
        return new DocumentationService($this->docsPath);
    });
});

afterEach(function () {
    if (is_dir($this->docsPath)) {
        File::deleteDirectory($this->docsPath);
    }
});

it('renders a public book page for guests', function () {
    $this->get('/library/books/test-book/part/chapter/public-page')
        ->assertOk()
        ->assertSee('Public content here.');
});

it('shows login message for users-only page when guest', function () {
    $this->get('/library/books/test-book/part/chapter/users-page')
        ->assertSee('Login Required');
});

it('renders users-only page when logged in', function () {
    $user = User::factory()->create();
    loginAs($user);

    $this->get('/library/books/test-book/part/chapter/users-page')
        ->assertOk()
        ->assertSee('Users only content.');
});

it('shows staff-only message for non-staff', function () {
    $user = User::factory()->create();
    loginAs($user);

    $this->get('/library/books/test-book/part/chapter/staff-page')
        ->assertSee('Access Restricted');
});

it('renders staff page for staff users', function () {
    $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew)->withRole('Staff Access')->create();
    loginAs($user);

    $this->get('/library/books/test-book/part/chapter/staff-page')
        ->assertOk()
        ->assertSee('Staff only content.');
});

it('returns 404 for non-existent page', function () {
    $this->get('/library/books/test-book/part/chapter/nonexistent')
        ->assertNotFound();
});

it('renders books index page', function () {
    $this->get('/library/books')
        ->assertOk()
        ->assertSee('Test Book');
});

it('renders guides index page', function () {
    $this->get('/library/guides')
        ->assertOk()
        ->assertSee('Test Guide');
});

it('filters invisible books from index for guests', function () {
    $this->get('/library/books')
        ->assertOk()
        ->assertSee('Test Book')
        ->assertDontSee('Staff Book');
});
