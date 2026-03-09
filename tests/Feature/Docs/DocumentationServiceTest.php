<?php

declare(strict_types=1);

use App\Services\DocumentationService;
use Illuminate\Support\Facades\File;

uses()->group('docs', 'services');

function createDocsFixture(): string
{
    $tmpDir = sys_get_temp_dir().'/lighthouse-docs-test-'.uniqid();
    mkdir($tmpDir.'/books/test-book/01-first-part/01-basics', 0755, true);
    mkdir($tmpDir.'/guides/test-guide', 0755, true);

    // Book _index.md
    file_put_contents($tmpDir.'/books/test-book/_index.md', <<<'MD'
---
title: "Test Book"
visibility: public
order: 1
summary: "A test book."
---

Book body content.
MD);

    // Part _index.md
    file_put_contents($tmpDir.'/books/test-book/01-first-part/_index.md', <<<'MD'
---
title: "First Part"
order: 1
summary: "The first part."
---

Part body content.
MD);

    // Chapter _index.md
    file_put_contents($tmpDir.'/books/test-book/01-first-part/01-basics/_index.md', <<<'MD'
---
title: "Basics"
order: 1
summary: "The basics chapter."
---

Chapter body content.
MD);

    // Pages
    file_put_contents($tmpDir.'/books/test-book/01-first-part/01-basics/01-intro.md', <<<'MD'
---
title: "Introduction"
visibility: public
order: 1
summary: "An intro page."
---

Hello world!
MD);

    file_put_contents($tmpDir.'/books/test-book/01-first-part/01-basics/02-next-steps.md', <<<'MD'
---
title: "Next Steps"
order: 2
summary: "What to do next."
---

Next steps content.
MD);

    // Staff-only page
    file_put_contents($tmpDir.'/books/test-book/01-first-part/01-basics/03-staff-only.md', <<<'MD'
---
title: "Staff Only Page"
visibility: staff
order: 3
summary: "For staff eyes only."
---

Secret staff content.
MD);

    // Guide _index.md
    file_put_contents($tmpDir.'/guides/test-guide/_index.md', <<<'MD'
---
title: "Test Guide"
visibility: users
order: 1
summary: "A test guide."
---

Guide intro.
MD);

    // Guide page
    file_put_contents($tmpDir.'/guides/test-guide/01-first-steps.md', <<<'MD'
---
title: "First Steps"
order: 1
summary: "Your first steps."
---

Guide first steps.
MD);

    return $tmpDir;
}

function cleanDocsFixture(string $path): void
{
    if (is_dir($path)) {
        File::deleteDirectory($path);
    }
}

beforeEach(function () {
    $this->docsPath = createDocsFixture();
    $this->service = new DocumentationService($this->docsPath);
});

afterEach(function () {
    cleanDocsFixture($this->docsPath);
});

it('discovers books from directory structure', function () {
    $books = $this->service->getAllBooks();

    expect($books)->toHaveCount(1);
    expect($books->first()->title)->toBe('Test Book');
    expect($books->first()->slug)->toBe('test-book');
});

it('discovers guides from directory structure', function () {
    $guides = $this->service->getAllGuides();

    expect($guides)->toHaveCount(1);
    expect($guides->first()->title)->toBe('Test Guide');
    expect($guides->first()->slug)->toBe('test-guide');
});

it('parses front matter correctly', function () {
    $result = $this->service->parseFile($this->docsPath.'/books/test-book/_index.md');

    expect($result['meta']['title'])->toBe('Test Book');
    expect($result['meta']['visibility'])->toBe('public');
    expect($result['meta']['order'])->toBe(1);
    expect($result['body'])->toContain('Book body content.');
});

it('handles missing front matter gracefully with defaults', function () {
    $noFrontMatter = $this->docsPath.'/no-front-matter.md';
    file_put_contents($noFrontMatter, 'Just some content without front matter.');

    $result = $this->service->parseFile($noFrontMatter);

    expect($result['meta'])->toBe([]);
    expect($result['body'])->toBe('Just some content without front matter.');
});

it('handles malformed YAML without crashing', function () {
    $badYaml = $this->docsPath.'/bad-yaml.md';
    file_put_contents($badYaml, "---\ntitle: [invalid yaml\n---\n\nContent here.");

    $result = $this->service->parseFile($badYaml);

    expect($result['meta'])->toBe([]);
    expect($result['body'])->toContain('Content here.');
});

it('resolves book page by slugs', function () {
    $page = $this->service->findBookPage('test-book', 'first-part', 'basics', 'intro');

    expect($page)->not->toBeNull();
    expect($page->title)->toBe('Introduction');
    expect($page->body)->toContain('Hello world!');
});

it('resolves guide page by slugs', function () {
    $page = $this->service->findGuidePage('test-guide', 'first-steps');

    expect($page)->not->toBeNull();
    expect($page->title)->toBe('First Steps');
    expect($page->body)->toContain('Guide first steps.');
});

it('strips numeric prefixes from slugs', function () {
    $book = $this->service->getBook('test-book');

    expect($book->parts->first()->slug)->toBe('first-part');
    expect($book->parts->first()->chapters->first()->slug)->toBe('basics');
});

it('builds book navigation tree correctly', function () {
    $nav = $this->service->getBookNavigation('test-book');

    expect($nav)->toHaveCount(1); // 1 part
    expect($nav[0]['label'])->toBe('First Part');
    expect($nav[0]['children'])->toHaveCount(1); // 1 chapter
    expect($nav[0]['children'][0]['label'])->toBe('Basics');
    expect($nav[0]['children'][0]['children'])->toHaveCount(3); // 3 pages
});

it('returns null for non-existent pages', function () {
    $page = $this->service->findBookPage('test-book', 'first-part', 'basics', 'nonexistent');

    expect($page)->toBeNull();
});

it('calculates previous and next pages correctly', function () {
    $adjacent = $this->service->getAdjacentPages('book', 'test-book', 'first-part', 'basics', 'intro');

    expect($adjacent['prev'])->toBeNull();
    expect($adjacent['next'])->not->toBeNull();
    expect($adjacent['next']->title)->toBe('Next Steps');
});

it('validates docs paths and rejects path traversal', function () {
    expect($this->service->isValidDocsPath('books/test-book/_index.md'))->toBeTrue();
    expect($this->service->isValidDocsPath('../../etc/passwd'))->toBeFalse();
    expect($this->service->isValidDocsPath('../../../etc/shadow'))->toBeFalse();
});

it('orders pages by numeric prefix', function () {
    $book = $this->service->getBook('test-book');
    $pages = $book->parts->first()->chapters->first()->pages;

    expect($pages[0]->title)->toBe('Introduction');
    expect($pages[1]->title)->toBe('Next Steps');
    expect($pages[2]->title)->toBe('Staff Only Page');
});

it('inherits visibility from parent when page has none', function () {
    // The "Next Steps" page has no visibility set, should inherit from book (public)
    $page = $this->service->findBookPage('test-book', 'first-part', 'basics', 'next-steps');

    expect($page->visibility)->toBe('public');

    // Guide page should inherit from guide (users)
    $guidePage = $this->service->findGuidePage('test-guide', 'first-steps');
    expect($guidePage->visibility)->toBe('users');
});

it('saves page content back to file', function () {
    $this->service->savePage('books/test-book/_index.md', [
        'title' => 'Updated Book',
        'visibility' => 'staff',
        'order' => 2,
        'summary' => 'Updated summary.',
    ], 'Updated body.');

    $result = $this->service->parseFile($this->docsPath.'/books/test-book/_index.md');

    expect($result['meta']['title'])->toBe('Updated Book');
    expect($result['meta']['visibility'])->toBe('staff');
    expect($result['body'])->toBe('Updated body.');
});

it('creates new page with correct front matter', function () {
    $this->service->createPage('books/test-book/01-first-part/01-basics', '04-new-page.md', [
        'title' => 'New Page',
        'visibility' => 'public',
        'order' => 4,
        'summary' => 'A new page.',
    ], 'New page content.');

    $filePath = $this->docsPath.'/books/test-book/01-first-part/01-basics/04-new-page.md';
    expect(file_exists($filePath))->toBeTrue();

    $result = $this->service->parseFile($filePath);
    expect($result['meta']['title'])->toBe('New Page');
    expect($result['body'])->toBe('New page content.');
});
